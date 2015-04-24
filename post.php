<?php
/*
Plugin Name: PBP Own post, media & comments for Author
Plugin URI: http://projoktibangla.net
Description: This plugin allows to restrict user with Author and Contributor roles to view their own Posts , Media and comments.
Author: projoktibangla
Version: 2.2
Author URI: http://projoktibangla.net
Tags: own, post, media, comments, author, contributor
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
/*
Copyright (C) 2013  projoktibangla

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


// Show only posts and media related to logged in author
add_action('pre_get_posts', 'pbp_query_set_only_author' );
function pbp_query_set_only_author( $wp_query ) {
    global $current_user;
    if( is_admin() && !current_user_can('edit_others_posts') ) {
        $wp_query->set( 'author', $current_user->ID );
        add_filter('views_edit-post', 'fix_post_counts');
        add_filter('views_upload', 'fix_media_counts');
    }
}

// Fix post counts
function fix_post_counts($views) {
    global $current_user, $wp_query;
    unset($views['mine']);
    $types = array(
        array( 'status' =>  NULL ),
        array( 'status' => 'publish' ),
        array( 'status' => 'draft' ),
        array( 'status' => 'pending' ),
        array( 'status' => 'trash' )
    );
    foreach( $types as $type ) {
        $query = array(
            'author'      => $current_user->ID,
            'post_type'   => 'post',
            'post_status' => $type['status']
        );
        $result = new WP_Query($query);
        if( $type['status'] == NULL ):
            $class = ($wp_query->get_query_var['post_status'] == NULL) ? ' class="current"' : '';
            $views['all'] = sprintf(
            '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
            admin_url('edit.php?post_type=post'),
            $class,
            $result->found_posts,
            __('All')
        );
        elseif( $type['status'] == 'publish' ):
            $class = ($wp_query->get_query_var['post_status'] == 'publish') ? ' class="current"' : '';
            $views['publish'] = sprintf(
            '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
            admin_url('edit.php?post_type=post'),
            $class,
            $result->found_posts,
            __('Publish')
        );
        elseif( $type['status'] == 'draft' ):
            $class = ($wp_query->get_query_var['post_status'] == 'draft') ? ' class="current"' : '';
            $views['draft'] = sprintf(
            '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
            admin_url('edit.php?post_type=post'),
            $class,
            $result->found_posts,
            __('Draft')
        );
        elseif( $type['status'] == 'pending' ):
            $class = ($wp_query->get_query_var['post_status'] == 'pending') ? ' class="current"' : '';
            $views['pending'] = sprintf(
            '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
            admin_url('edit.php?post_type=post'),
            $class,
            $result->found_posts,
            __('Pending')
        );
        elseif( $type['status'] == 'trash' ):
            $class = ($wp_query->get_query_var['post_status'] == 'trash') ? ' class="current"' : '';
            $views['trash'] = sprintf(
            '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
            admin_url('edit.php?post_type=post'),
            $class,
            $result->found_posts,
            __('Trash')
        );
        endif;
    }
    return $views;
}

// Fix media counts
function fix_media_counts($views) {
    global $wpdb, $current_user, $post_mime_types, $avail_post_mime_types;
    $views = array();
    $count = $wpdb->get_results( "
        SELECT post_mime_type, COUNT( * ) AS num_posts 
        FROM $wpdb->posts 
        WHERE post_type = 'attachment' 
        AND post_author = $current_user->ID 
        AND post_status != 'trash' 
        GROUP BY post_mime_type
    ", ARRAY_A );
    foreach( $count as $row )
        $_num_posts[$row['post_mime_type']] = $row['num_posts'];
    $_total_posts = array_sum($_num_posts);
    $detached = isset( $_REQUEST['detached'] ) || isset( $_REQUEST['find_detached'] );
    if ( !isset( $total_orphans ) )
        $total_orphans = $wpdb->get_var("
            SELECT COUNT( * ) 
            FROM $wpdb->posts 
            WHERE post_type = 'attachment'
            AND post_author = $current_user->ID 
            AND post_status != 'trash' 
            AND post_parent < 1
        ");
    $matches = wp_match_mime_types(array_keys($post_mime_types), array_keys($_num_posts));
    foreach ( $matches as $type => $reals )
        foreach ( $reals as $real )
            $num_posts[$type] = ( isset( $num_posts[$type] ) ) ? $num_posts[$type] + $_num_posts[$real] : $_num_posts[$real];
    $class = ( empty($_GET['post_mime_type']) && !$detached && !isset($_GET['status']) ) ? ' class="current"' : '';
    $views['all'] = "<a href='upload.php'$class>" . sprintf( __('All <span class="count">(%s)</span>', 'uploaded files' ), number_format_i18n( $_total_posts )) . '</a>';
    foreach ( $post_mime_types as $mime_type => $label ) {
        $class = '';
        if ( !wp_match_mime_types($mime_type, $avail_post_mime_types) )
            continue;
        if ( !empty($_GET['post_mime_type']) && wp_match_mime_types($mime_type, $_GET['post_mime_type']) )
            $class = ' class="current"';
        if ( !empty( $num_posts[$mime_type] ) )
            $views[$mime_type] = "<a href='upload.php?post_mime_type=$mime_type'$class>" . sprintf( translate_nooped_plural( $label[2], $num_posts[$mime_type] ), $num_posts[$mime_type] ) . '</a>';
    }
    $views['detached'] = '<a href="upload.php?detached=1"' . ( $detached ? ' class="current"' : '' ) . '>' . sprintf( __( 'Unattached <span class="count">(%s)</span>', 'detached files' ), $total_orphans ) . '</a>';
    return $views;
}


# ------------------------------------------------------------
# Ensure that non-admins can see and manage only their own comments
# ------------------------------------------------------------


add_filter('the_comments', 'pbp_filter_comments');

function pbp_filter_comments($comments){
    global $pagenow;
    global $user_ID;
    get_currentuserinfo();
    if($pagenow == 'edit-comments.php' && current_user_can('author')){
        foreach($comments as $i => $comment){
            $the_post = get_post($comment->comment_post_ID);
            if($comment->user_id != $user_ID  && $the_post->post_author != $user_ID)
                unset($comments[$i]);
        }
    }
    return $comments;
}
?>