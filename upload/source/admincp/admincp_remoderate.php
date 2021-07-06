<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id$
 */

if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

cpheader();

$pertask = isset($_GET['pertask']) ? intval($_GET['pertask']) : 100;
$current = isset($_GET['current']) && $_GET['current'] > 0 ? intval($_GET['current']) : 0;
$next = $current + $pertask;

if(submitcheck('threadsubmit', 1)) {

	// 主题/帖子标题及内容重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&threadsubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('forum_thread')->fetch_all_by_displayorder(0, '>=', $current, $pertask) as $thread) {
		$processed = 1;
		foreach(C::t('forum_post')->fetch_all_visiblepost_by_tid($thread['posttableid'], $thread['tid']) as $post) {
			if($censor->check($post['subject']) || $censor->check($post['message'])) {
				C::t('forum_post')->update($thread['posttableid'], $post['pid'], array('status' => 4), false, false, null, -2, null, 0);
				if($post['first'] == 1) {
					C::t('forum_thread')->update($thread['tid'], array('displayorder' => -2));
					updatemoderate('tid', $thread['tid']);
				} else {
					updatemoderate('pid', $post['pid']);
				}
			}
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_thread']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_thread_succeed', 'action=remoderate', 'succeed');
	}

} elseif(submitcheck('blogsubmit', 1)) {

	// 日志标题及内容重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&blogsubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('home_blog')->range($current, $pertask, 'ASC', 'dateline', null, 0) as $blog) {
		$processed = 1;
		$post = C::t('home_blogfield')->fetch($blog['blogid']);
		if($censor->check($blog['subject']) || $censor->check($post['message'])) {
			C::t('home_blog')->update($blog['blogid'], array('status' => 1));
			updatemoderate('blogid', $blog['blogid']);
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_blog']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_blog_succeed', 'action=remoderate', 'succeed');
	}

} elseif(submitcheck('picsubmit', 1)) {

	// 图片标题重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&picsubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('home_pic')->fetch_all_by_sql('`status` = 0', 'picid', $current, $pertask, 0, 0) as $pic) {
		$processed = 1;
		if($censor->check($pic['title'])) {
			C::t('home_pic')->update($pic['picid'], array('status' => 1));
			updatemoderate('picid', $pic['picid']);
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_pic']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_pic_succeed', 'action=remoderate', 'succeed');
	}

} elseif(submitcheck('doingsubmit', 1)) {

	// 记录内容重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&doingsubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('home_doing')->fetch_all_by_status(0, $current, $pertask) as $doing) {
		$processed = 1;
		if($censor->check($doing['message'])) {
			C::t('home_doing')->update($doing['doid'], array('status' => 1));
			updatemoderate('doid', $doing['doid']);
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_doing']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_doing_succeed', 'action=remoderate', 'succeed');
	}

} elseif(submitcheck('sharesubmit', 1)) {

	// 分享内容重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&sharesubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('home_share')->fetch_all_by_status(0, $current, $pertask) as $share) {
		$processed = 1;
		if($censor->check($share['body_general'])) {
			C::t('home_share')->update($share['sid'], array('status' => 1));
			updatemoderate('sid', $share['sid']);
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_share']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_share_succeed', 'action=remoderate', 'succeed');
	}

} elseif(submitcheck('commentsubmit', 1)) {

	// 家园评论内容重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&commentsubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('home_comment')->fetch_all_by_status(0, $current, $pertask) as $comment) {
		$processed = 1;
		if($censor->check($comment['message'])) {
			C::t('home_comment')->update($comment['cid'], array('status' => 1));
			updatemoderate($comment['idtype'].'_cid', $comment['cid']);
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_comment']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_comment_succeed', 'action=remoderate', 'succeed');
	}

} elseif(submitcheck('articlesubmit', 1)) {

	// 文章标题及内容重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&articlesubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('portal_article_title')->fetch_all_by_sql('`status` = 0', '', $current, $pertask) as $article) {
		$processed = 1;
		if(!$censor->check($post['subject'])) {
			foreach(C::t('portal_article_content')->fetch_all($article['aid']) as $post) {
				if($censor->check($post['content'])) {
					C::t('portal_article_title')->update($article['aid'], array('status' => 1));
					updatemoderate('aid', $article['aid']);
					break;
				}
			}
		} else {
			C::t('portal_article_title')->update($article['aid'], array('status' => 1));
			updatemoderate('aid', $article['aid']);
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_article']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_article_succeed', 'action=remoderate', 'succeed');
	}

} elseif(submitcheck('articlecommentsubmit', 1)) {

	// 文章评论内容重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&articlecommentsubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('portal_comment')->fetch_all_by_idtype_status('aid', 0, $current, $pertask) as $comment) {
		$processed = 1;
		if($censor->check($comment['message'])) {
			C::t('portal_comment')->update($comment['cid'], array('status' => 1));
			updatemoderate($comment['idtype'].'_cid', $comment['cid']);
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_articlecomment']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_articlecomment_succeed', 'action=remoderate', 'succeed');
	}

} elseif(submitcheck('topiccommentsubmit', 1)) {

	// 专题评论内容重新审核

	$nextlink = "action=remoderate&current=$next&pertask=$pertask&topiccommentsubmit=yes";
	$processed = 0;

	$censor = & discuz_censor::instance();

	foreach(C::t('portal_comment')->fetch_all_by_idtype_status('topicid', 0, $current, $pertask) as $comment) {
		$processed = 1;
		if($censor->check($comment['message'])) {
			C::t('portal_comment')->update($comment['cid'], array('status' => 1));
			updatemoderate($comment['idtype'].'_cid', $comment['cid']);
		}
	}

	if($processed) {
		cpmsg("{$lang['remoderate_topiccomment']}: ".cplang('remoderate_processing', array('current' => $current, 'next' => $next)), $nextlink, 'loading');
	} else {
		cpmsg('remoderate_topiccomment_succeed', 'action=remoderate', 'succeed');
	}

} else {

	shownav('topic', 'nav_remoderate');
	showsubmenu('nav_remoderate');
	/*search={"nav_remoderate":"action=remoderate"}*/
	showtips('remoderate_tips');
	/*search*/
	showformheader('remoderate');
	showtableheader();
	showsubtitle(array('', 'remoderate_amount'));
	showhiddenfields(array('pertask' => ''));

	// 主题/帖子标题及内容重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_thread']}:",
		'<input name="pertask1" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="threadsubmit" onclick="this.form.pertask.value=this.form.pertask1.value" value="'.$lang['submit'].'" />'
	));
	// 日志标题及内容重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_blog']}:",
		'<input name="pertask2" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="blogsubmit" onclick="this.form.pertask.value=this.form.pertask2.value" value="'.$lang['submit'].'" />'
	));
	// 图片标题重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_pic']}:",
		'<input name="pertask3" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="picsubmit" onclick="this.form.pertask.value=this.form.pertask3.value" value="'.$lang['submit'].'" />'
	));
	// 记录内容重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_doing']}:",
		'<input name="pertask4" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="doingsubmit" onclick="this.form.pertask.value=this.form.pertask4.value" value="'.$lang['submit'].'" />'
	));
	// 分享内容重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_share']}:",
		'<input name="pertask5" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="sharesubmit" onclick="this.form.pertask.value=this.form.pertask5.value" value="'.$lang['submit'].'" />'
	));
	// 家园评论内容重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_comment']}:",
		'<input name="pertask6" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="commentsubmit" onclick="this.form.pertask.value=this.form.pertask6.value" value="'.$lang['submit'].'" />'
	));
	// 文章标题及内容重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_article']}:",
		'<input name="pertask7" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="articlesubmit" onclick="this.form.pertask.value=this.form.pertask7.value" value="'.$lang['submit'].'" />'
	));
	// 文章评论内容重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_articlecomment']}:",
		'<input name="pertask8" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="articlecommentsubmit" onclick="this.form.pertask.value=this.form.pertask8.value" value="'.$lang['submit'].'" />'
	));
	// 专题评论内容重新审核
	showtablerow('', array('class="td31 bold"'), array(
		"{$lang['remoderate_topiccomment']}:",
		'<input name="pertask9" type="text" class="txt" value="1000" /><input type="submit" class="btn" name="topiccommentsubmit" onclick="this.form.pertask.value=this.form.pertask9.value" value="'.$lang['submit'].'" />'
	));

	showtablefooter();
	showformfooter();

}