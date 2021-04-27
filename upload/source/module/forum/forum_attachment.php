<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: forum_attachment.php 34304 2014-01-15 11:11:23Z nemohou $
 */

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
define('NOROBOT', TRUE);
@list($_GET['aid'], $_GET['k'], $_GET['t'], $_GET['uid'], $_GET['tableid']) = daddslashes(explode('|', base64_decode($_GET['aid'])));

$requestmode = !empty($_GET['request']) && empty($_GET['uid']);
$aid = intval($_GET['aid']);
$k = $_GET['k'];
$t = $_GET['t'];
$authk = !$requestmode ? substr(md5($aid.md5($_G['config']['security']['authkey']).$t.$_GET['uid']), 0, 8) : md5($aid.md5($_G['config']['security']['authkey']).$t);

if($k != $authk) {
    if(!$requestmode) {
        showmessage('attachment_nonexistence');
    } else {
        exit;
    }
}

if(!empty($_GET['findpost']) && ($attach = C::t('forum_attachment')->fetch($aid))) {
	dheader('location: forum.php?mod=redirect&goto=findpost&pid='.$attach['pid'].'&ptid='.$attach['tid']);
}

if($_GET['uid'] != $_G['uid'] && $_GET['uid']) {
	$_G['uid'] = $_GET['uid'] = intval($_GET['uid']);
	$member = getuserbyuid($_GET['uid']);
	loadcache('usergroup_'.$member['groupid']);
	$_G['group'] = $_G['cache']['usergroup_'.$member['groupid']];
	$_G['group']['grouptitle'] = $_G['cache']['usergroup_'.$_G['groupid']]['grouptitle'];
	$_G['group']['color'] = $_G['cache']['usergroup_'.$_G['groupid']]['color'];
}


$tableid = 'aid:'.$aid;

if($_G['setting']['attachexpire']) {

	if(TIMESTAMP - $t > $_G['setting']['attachexpire'] * 3600) {
		$aid = intval($aid);
		if($attach = C::t('forum_attachment_n')->fetch_attachment($tableid, $aid)) {
			if($attach['isimage']) {
				dheader('location: '.$_G['siteurl'].'static/image/common/none.gif');
			} else {
				if(!$requestmode) {
					showmessage('attachment_expired', '', array('aid' => aidencode($aid, 0, $attach['tid']), 'pid' => $attach['pid'], 'tid' => $attach['tid']));
				} else {
					exit;
				}
			}
		} else {
			if(!$requestmode) {
				showmessage('attachment_nonexistence');
			} else {
				exit;
			}
		}
	}
}

$readmod = getglobal('config/download/readmod');
$readmod = $readmod > 0 && $readmod < 5 ? $readmod : 2;

$refererhost = parse_url($_SERVER['HTTP_REFERER']);
$serverhost = $_SERVER['HTTP_HOST'];
if(($pos = strpos($serverhost, ':')) !== FALSE) {
	$serverhost = substr($serverhost, 0, $pos);
}

if(!$requestmode && $_G['setting']['attachrefcheck'] && $_SERVER['HTTP_REFERER'] && !($refererhost['host'] == $serverhost)) {
	showmessage('attachment_referer_invalid', NULL);
}

periodscheck('attachbanperiods');

// 获取 thread 分表
loadcache('threadtableids');
$threadtableids = !empty($_G['cache']['threadtableids']) ? $_G['cache']['threadtableids'] : array();
if(!in_array(0, $threadtableids)) {
	$threadtableids = array_merge(array(0), $threadtableids);
}
$archiveid = in_array($_GET['archiveid'], $threadtableids) ? intval($_GET['archiveid']) : 0;

// 检查附件 aid 数据记录，取得附件和主题信息
$attachexists = FALSE;
if(!empty($aid) && is_numeric($aid)) {
	$attach = C::t('forum_attachment_n')->fetch_attachment($tableid, $aid);
	$thread = C::t('forum_thread')->fetch_by_tid_displayorder($attach['tid'], 0, '>=', null, $archiveid);
	if($_G['uid'] && $attach['uid'] != $_G['uid']) {
		if($attach) {
			$attachpost = C::t('forum_post')->fetch_post($thread['posttableid'], $attach['pid'], false);
			$attach['invisible'] = $attachpost['invisible'];
			unset($attachpost);
		}
		if($attach && $attach['invisible'] == 0) {
			$thread && $attachexists = TRUE;
		}
	} else {
		$attachexists = TRUE;
	}
}

if(!$attachexists) {
	if(!$requestmode) {
		showmessage('attachment_nonexistence');
	} else {
		exit;
	}
}

if(!$requestmode) {
	// 获取附件所在版块信息
	$forum = C::t('forum_forumfield')->fetch_info_for_attach($thread['fid'], $_G['uid']);
	$_GET['fid'] = $forum['fid'];

	// 判断附件下载权限
	if($attach['isimage']) {
		$allowgetattach = ($_G['uid'] == $attach['uid']) ? true : ((!empty($forum['allowgetimage'])) ? ($forum['allowgetimage'] == 1 ? true : false) : ($forum['getattachperm'] ? forumperm($forum['getattachperm']) : $_G['group']['allowgetimage']));
	} else {
		$allowgetattach = ($_G['uid'] == $attach['uid']) ? true : ((!empty($forum['allowgetattach'])) ? ($forum['allowgetattach'] == 1 ? true : false) : ($forum['getattachperm'] ? forumperm($forum['getattachperm']) : $_G['group']['allowgetattach']));
	}
	if(($attach['readperm'] && $attach['readperm'] > $_G['group']['readaccess']) && $_G['adminid'] <= 0 && !($_G['uid'] && $_G['uid'] == $attach['uid'])) {
		$allowgetattach = FALSE;
		showmessage('attachment_forum_nopermission', NULL, array(), array('login' => 1));
	}

	$ismoderator = in_array($_G['adminid'], array(1, 2)) ? 1 : ($_G['adminid'] == 3 ? C::t('forum_moderator')->fetch_uid_by_tid($attach['tid'], $_G['uid'], $archiveid) : 0);

	// 检查附件所在主题是否付费
	$ispaid = FALSE;
	$exemptvalue = $ismoderator ? 128 : 16;
	if(!$thread['special'] && $thread['price'] > 0 && (!$_G['uid'] || ($_G['uid'] != $attach['uid'] && !($_G['group']['exempt'] & $exemptvalue)))) {
		if(!$_G['uid'] || $_G['uid'] && !($ispaid = C::t('common_credit_log')->count_by_uid_operation_relatedid($_G['uid'], 'BTC', $attach['tid']))) {
			showmessage('attachment_payto', 'forum.php?mod=viewthread&tid='.$attach['tid']);
		}
	}

	// 检查收费附件及购买记录查询
	$exemptvalue = $ismoderator ? 64 : 8;
	if($attach['price'] && (!$_G['uid'] || ($_G['uid'] != $attach['uid'] && !($_G['group']['exempt'] & $exemptvalue)))) {
		$payrequired = $_G['uid'] ? !C::t('common_credit_log')->count_by_uid_operation_relatedid($_G['uid'], 'BAC', $attach['aid']) : 1;
		$payrequired && showmessage('attachement_payto_attach', 'forum.php?mod=misc&action=attachpay&aid='.$attach['aid'].'&tid='.$attach['tid']);
	}

	// 检查版块权限，如果该主题已付费，则放行
	if(!$ispaid && !$allowgetattach) {
		if(($forum['getattachperm'] && !forumperm($forum['getattachperm'])) || ($forum['viewperm'] && !forumperm($forum['viewperm']))) {
			showmessagenoperm('getattachperm', $forum['fid']);
		} else {
			showmessage('getattachperm_none_nopermission', NULL, array(), array('login' => 1));
		}
	}
}

$isimage = $attach['isimage'];
$_G['setting']['ftp']['hideurl'] = $_G['setting']['ftp']['hideurl'] || ($isimage && !empty($_GET['noupdate']) && $_G['setting']['attachimgpost'] && strtolower(substr($_G['setting']['ftp']['attachurl'], 0, 3)) == 'ftp');

// 输出图片附件的预览图
if(empty($_GET['nothumb']) && $attach['isimage'] && $attach['thumb']) {
	$db = DB::object();
	$db->close();
	!$_G['config']['output']['gzip'] && ob_end_clean();
	dheader('Content-Disposition: inline; filename='.getimgthumbname($attach['filename']));
	dheader('Content-Type: image/pjpeg');
	if($attach['remote']) {
		$_G['setting']['ftp']['hideurl'] ? getremotefile(getimgthumbname($attach['attachment'])) : dheader('location:'.$_G['setting']['ftp']['attachurl'].'forum/'.getimgthumbname($attach['attachment']));
	} else {
		getlocalfile($_G['setting']['attachdir'].'/forum/'.getimgthumbname($attach['attachment']));
	}
	exit();
}

$filename = $_G['setting']['attachdir'].'/forum/'.$attach['attachment'];
if(!$attach['remote'] && !is_readable($filename)) {	
	if(!$requestmode) {
		showmessage('attachment_nonexistence');
	} else {
		exit;
	}
}

if(!$requestmode) {
	// 非图片附件下载进行积分检查
	$exemptvalue = $ismoderator ? 32 : 4;
	if(!$isimage && !($_G['group']['exempt'] & $exemptvalue)) {
		$creditlog = updatecreditbyaction('getattach', $_G['uid'], array(), '', 1, 0, $thread['fid']);
		if($creditlog['updatecredit']) {
			if($_G['uid']) {
				$k = $_GET['ck'];
				$t = $_GET['t'];
				if(empty($k) || empty($t) || $k != substr(md5($aid.$t.md5($_G['config']['security']['authkey'])), 0, 8) || TIMESTAMP - $t > 3600) {
					dheader('location: forum.php?mod=misc&action=attachcredit&aid='.$attach['aid'].'&formhash='.FORMHASH);
					exit();
				}
			} else {
				showmessage('attachment_forum_nopermission', NULL, array(), array('login' => 1));
			}
		}
	}
}

// 多线程下载支持
// 解析 range 的范围，readmod = 1 or 4 的时候，支持 range
// range 传入有可能没有 end，这时候要在获取了文件大小后，根据文件大小设置 range_end
$range_start = 0;
$range_end = 0;
$has_range_header = false;
if(($readmod == 4 || $readmod == 1) && !empty($_SERVER['HTTP_RANGE'])) {
	$has_range_header = true;
	list($range_start, $range_end) = explode('-',(str_replace('bytes=', '', $_SERVER['HTTP_RANGE'])));
}

// 更新附件下载次数
if(!$requestmode && !$has_range_header && empty($_GET['noupdate'])) {
	if($_G['setting']['delayviewcount']) {
		$_G['forum_logfile'] = './data/cache/forum_attachviews_'.intval(getglobal('config/server/id')).'.log';
		if(substr(TIMESTAMP, -1) == '0') {
			attachment_updateviews($_G['forum_logfile']);
		}

		if(@$fp = fopen(DISCUZ_ROOT.$_G['forum_logfile'], 'a')) {
			fwrite($fp, "$aid\n");
			fclose($fp);
		} elseif($_G['adminid'] == 1) {
			showmessage('view_log_invalid', '', array('logfile' => $_G['forum_logfile']));
		}
	} else {
		C::t('forum_attachment')->update_download($aid);
	}
}

// 关闭数据库及输出附件内容
$db = DB::object();
$db->close();
!$_G['config']['output']['gzip'] && ob_end_clean();


if($attach['remote'] && !$_G['setting']['ftp']['hideurl'] && $isimage) {
	dheader('location:'.$_G['setting']['ftp']['attachurl'].'forum/'.$attach['attachment']);
}

// 获取支持h5媒体播放的mimetype，Safari要有mimetype和range支持才能正确播放
$mimetype = ext_to_mimetype($attach['filename']);
$filesize = !$attach['remote'] ? filesize($filename) : $attach['filesize'];
// 如果range_end没有传入，更新range_end
if ($has_range_header && !$range_end) $range_end = $filesize - 1;
$filenameencode = strtolower(CHARSET) == 'utf-8' ? rawurlencode($attach['filename']) : rawurlencode(diconv($attach['filename'], CHARSET, 'UTF-8'));

dheader('Date: '.gmdate('D, d M Y H:i:s', $attach['dateline']).' GMT');
dheader('Last-Modified: '.gmdate('D, d M Y H:i:s', $attach['dateline']).' GMT');
dheader('Content-Encoding: none');

if($isimage && !empty($_GET['noupdate']) || !empty($_GET['request'])) {
	dheader('Content-Disposition: inline; filename="'.(($attach['filename'] == $filenameencode) ? $attach['filename'].'"' : $filenameencode.'"; filename*=utf-8\'\''.$filenameencode));
} else {
	dheader('Content-Disposition: attachment; filename="'.(($attach['filename'] == $filenameencode) ? $attach['filename'].'"' : $filenameencode.'"; filename*=utf-8\'\''.$filenameencode));
}
if($isimage) {
	dheader('Content-Type: image');
} else {
	dheader('Content-Type: ' . $mimetype);
}

dheader('Content-Length: '.$filesize);

$xsendfile = getglobal('config/download/xsendfile');
if(!empty($xsendfile)) {
	$type = intval($xsendfile['type']);
	if($isimage){
		$type = 0;
	}
	$cmd = '';
	switch ($type) {
		case 1: $cmd = 'X-Accel-Redirect'; $url = $xsendfile['dir'].$attach['attachment']; break;
		case 2: $cmd = $_SERVER['SERVER_SOFTWARE'] <'lighttpd/1.5' ? 'X-LIGHTTPD-send-file' : 'X-Sendfile'; $url = $filename; break;
		case 3: $cmd = 'X-Sendfile'; $url = $filename; break;
	}
	if($cmd) {
		dheader("$cmd: $url");
		exit();
	}
}

// readmod = 1 or 4 的时候，支持Range
if (($readmod == 4) || ($readmod == 1)) {
	dheader('Accept-Ranges: bytes');
	if($has_range_header) {
		$rangesize = ($range_end - $range_start) >= 0 ?  ($range_end - $range_start) + 1 : 0;
		dheader('Content-Length: '.$rangesize);
		dheader('HTTP/1.1 206 Partial Content');
		dheader('Content-Range: bytes '.$range_start.'-'.$range_end.'/'.($filesize));
	}
}

$attach['remote'] ? getremotefile($attach['attachment']) : getlocalfile($filename, $readmod, $range_start, $range_end);

function getremotefile($file) {
	global $_G;
	@set_time_limit(0);
	if(!@readfile($_G['setting']['ftp']['attachurl'].'forum/'.$file)) {
		$ftp = ftpcmd('object');
		$tmpfile = @tempnam($_G['setting']['attachdir'], '');
		if(is_object($ftp) && $ftp->ftp_get($tmpfile, 'forum/'.$file, FTP_BINARY)) {
			@readfile($tmpfile);
			@unlink($tmpfile);
		} else {
			@unlink($tmpfile);
			return FALSE;
		}
	}
	return TRUE;
}

function getlocalfile($filename, $readmod = 2, $range_start = 0, $range_end = 0) {
	if($readmod == 1 || $readmod == 3 || $readmod == 4) {
		if($fp = @fopen($filename, 'rb')) {
			@fseek($fp, $range_start);
			if(function_exists('fpassthru') && ($readmod == 3 || $readmod == 4) && ($range_end <= 0)) {
				@fpassthru($fp);
			} else {
				if ($range_end > 0) {
					send_file_by_chunk($fp, $range_end - $range_start + 1);
				} else {
					send_file_by_chunk($fp);
				}
			}
		}
		@fclose($fp);
	} else {
		@readfile($filename);
	}
	@flush(); @ob_flush();
}

function send_file_by_chunk($fp, $limit = PHP_INT_MAX) {
	static $CHUNK_SIZE = 65536; // 每次最大读 64KB
	$count = 0;
	while (!feof($fp)) {
		$size_to_read = $CHUNK_SIZE;
		if ($count + $size_to_read > $limit) $size_to_read = $limit - $count;
		$buf = fread($fp, $size_to_read);
		echo $buf;
		flush();
		ob_flush();
		$count += sizeof($buf);
		if ($count >= $limit) break;
	}
}

function attachment_updateviews($logfile) {
	$viewlog = $viewarray = array();
	$newlog = DISCUZ_ROOT.$logfile.random(6);
	if(@rename(DISCUZ_ROOT.$logfile, $newlog)) {
		$viewlog = file($newlog);
		unlink($newlog);
		if(is_array($viewlog) && !empty($viewlog)) {
			$viewlog = array_count_values($viewlog);
			foreach($viewlog as $id => $views) {
				if($id > 0) {
					$viewarray[$views][] = intval($id);
				}
			}
			foreach($viewarray as $views => $ids) {
				C::t('forum_attachment')->update_download($ids, $views);
			}
		}
	}
}

function ext_to_mimetype($path) {
	$ext = pathinfo($path, PATHINFO_EXTENSION);
	$map = array(
		'aac' => 'audio/aac',
		'flac' => 'audio/flac', 
		'mp3' => 'audio/mpeg', 
		'm4a' => 'audio/mp4', 
		'wav' => 'audio/wav', 
		'ogg' => 'audio/ogg',
		'weba' => 'audio/webm',
		'flv' => 'video/x-flv', 
		'mp4' => 'video/mp4', 
		'm4v' => 'video/mp4', 
		'3gp' => 'video/3gpp',
		'ogv' => 'video/ogg',
		'webm' => 'video/webm' 
	);
	$mime = $map[$ext];
	if (!$mime) $mime = "application/octet-stream";
	return $mime;
}

?>