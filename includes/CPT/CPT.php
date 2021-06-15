<?php

namespace RRZE\Pieksy\CPT;

defined('ABSPATH') || exit;

use RRZE\Pieksy\Main;
use RRZE\Pieksy\Capabilities;
use RRZE\Pieksy\Functions;

/**
 * Laden und definieren der Posttypes
 */
class CPT extends Main
{
    public function __construct()
    {
        //
    }

    public function onLoaded()
    {
        $bookings = new Bookings;
        $bookings->onLoaded();

        $rooms = new Rooms;
        $rooms->onLoaded();

        $seats = new Seats;
        $seats->onLoaded();

        add_action('admin_menu', [$this, 'bookingMenu']);
        add_filter('parent_file', [$this, 'filterParentMenu']);
        add_action('pre_get_posts', [$this, 'archiveShowAllRooms']);
        add_action('add_meta_boxes', [$this, 'shortcodeHelper']);

        if (isset($_GET['format']) && $_GET['format'] == 'embedded') {
            add_filter(
                'body_class',
                function ($classes) {
                    return array_merge($classes, array('embedded'));
                }
            );
        }
    }

    public function activation()
    {
        $bookings = new Bookings;
        $bookings->booking_post_type();

        $rooms = new Rooms;
        $rooms->room_post_type();

        $seats = new Seats;
        $seats->seats_post_type();
    }

    public function bookingMenu()
    {
        $cpts = array_keys(Capabilities::getCurrentCptArgs());
        $hiddenTitle = 'rrze-pieksy-submenu-hidden';

        foreach ($cpts as $cpt) {
            $cpt_obj = get_post_type_object($cpt);
            add_submenu_page(
                'edit.php?post_type=booking',      // parent slug
                $cpt_obj->labels->name,            // page title
                $cpt_obj->labels->menu_name,       // menu title
                $cpt_obj->cap->edit_posts,         // capability
                'edit.php?post_type=' . $cpt       // menu slug
            );
        }

        add_submenu_page(
            'edit.php?post_type=booking',
            __('Room occupancy for today', 'rrze-pieksy'),
            __('Room occupancy', 'rrze-pieksy'),
            'edit_seats',
            'occupancy',
            [$this, 'getOccupancyPage']
        );


        remove_submenu_page('edit.php?post_type=booking', 'edit.php?post_type=booking');
        remove_submenu_page('edit.php?post_type=booking', 'post-new.php?post_type=booking');

        global $submenu;
        $hiddenClass = $hiddenTitle;
        if (isset($submenu['edit.php?post_type=booking'])) {
            foreach ($submenu['edit.php?post_type=booking'] as $key => $menu) {
                if ($menu[0] == $hiddenTitle) {
                    $submenu['edit.php?post_type=booking'][$key][4] = $hiddenClass;
                }
            }
        }
    }

    public function filterParentMenu($parent_file)
    {
        global $submenu_file, $current_screen, $pagenow;

        $cpts = array_keys(Capabilities::getCurrentCptArgs());

        foreach ($cpts as $cpt) {
            if ($current_screen->post_type == $cpt) {

                if ($pagenow == 'post.php') {
                    $submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
                }

                if ($pagenow == 'post-new.php') {
                    $submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
                }

                $parent_file = 'edit.php?post_type=booking';
            }
        }

        if ($current_screen->post_type == 'seat') {
            $parent_file = 'edit.php?post_type=booking';
        }

        return $parent_file;
    }

    public function getOccupancyPage()
    {
        echo '<div class="wrap">'
            . '<h1>' . esc_html_x('Room occupancy for today', 'admin page title', 'rrze-pieksy') . '</h1>'

            . '<div class="tablenav top">'
            . '<div class="alignleft actions bulkactions">'
            . '<label for="select_room" class="screen-reader-text">' . __('Room', 'rrze-pieksy') . '</label>'
            . '<form action="" method="post" class="occupancy">'
            . '<select id="pieksy_room_id" name="pieksy_room_id">'
            . '<option>&mdash; ' . __('Please select room', 'rrze-pieksy') . ' &mdash;</option>';

        $rooms = get_posts([
            'post_type' => 'room',
            'post_statue' => 'publish',
            'nopaging' => true,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        foreach ($rooms as $room) {
            echo '<option value="' . $room->ID . '">' . $room->post_title . '</option>';
        }
        echo '</select></form>'
            . '<div id="loading"><i class="fa fa-refresh fa-spin fa-4x"></i></div>'
            . '</div>'
            . '<div class="pieksy-occupancy-links"></div>'
            . '<div class="pieksy-occupancy-container"></div>'
            . '</div>';
    }

    public function customSubmitdiv()
    {
        // um zu verhindern, dass der Admin den Status des Posts ändert oder schlimmer noch, ihn versehentlich löscht. 
        remove_meta_box('submitdiv', 'booking', 'core');
        add_meta_box('submitdiv', __('Publish'), [$this, 'addCustomSubmitdiv'], 'booking', 'side', 'high');
        remove_meta_box('submitdiv', 'room', 'core');
        add_meta_box('submitdiv', __('Publish'), [$this, 'addCustomSubmitdiv'], 'room', 'side', 'high');          
        remove_meta_box('submitdiv', 'seat', 'core');
        add_meta_box('submitdiv', __('Publish'), [$this, 'addCustomSubmitdiv'], 'seat', 'side', 'high');
    }

    public function addCustomSubmitdiv()
    {
        global $post;
        $postType = $post->post_type;
        $postTypeObject = get_post_type_object($postType);
        $canPublish = current_user_can($postTypeObject->cap->publish_posts);
        $canDelete = Functions::canDeletePost($post->ID, $postType);
        ?>
        <div class="submitbox" id="submitpost">
            <div id="major-publishing-actions">
                <?php
                do_action('post_submitbox_start');
                ?>
                <div id="delete-action">
                <?php
                if ($canDelete && current_user_can('delete_post', $post->ID)) {
                    if (!EMPTY_TRASH_DAYS)
                        $delete_text = __('Delete Permanently');
                    else
                        $delete_text = __('Move to Trash');
                    ?>
                    <a class="submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a>
                    <?php
                }
                ?>
                </div>
                <div id="publishing-action">
                    <span class="spinner"></span>
                    <?php
                    if (!in_array($post->post_status, array('publish', 'future', 'private')) || 0 == $post->ID) {
                        if ($canPublish) : ?>
                            <input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Add Tab') ?>" />
                            <?php submit_button(__('Publish'), 'primary button-large', 'publish', false); ?>
                        <?php
                        endif;
                    } else { ?>
                        <input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Update'); ?>" />
                        <input name="save" type="submit" class="button button-primary button-large" id="publish" value="<?php esc_attr_e('Update'); ?>">
                    <?php
                    }
                    ?>
                </div>
                <div class="clear"></div>
            </div>
        </div>
        <?php
    }

    public function shortcodeHelper()
    {
        add_meta_box('rrze-pieksy-room-shortcode-helper', esc_html__('Shortcodes', 'rrze-pieksy'), [$this, 'shortcodeHelperCallback'], 'room', 'side', 'high');
    }

    public function shortcodeHelperCallback()
    {
        printf('<p class="description">%s</p>', __('You can display a booking form or a table with the available time slots for this room by adding one of the following shortcodes to any page:', 'rrze-pieksy'));
        printf('<h3 style="margin-bottom: 0">%s</h3>', __('Booking Form', 'rrze-pieksy'));
        printf('<p><code>[pieksy-booking room="%s"]</code></p>', get_the_ID());
        printf('<p>%s</p>', __('Add <code>days="20"</code> to overwrite the number of days you can book a seat in advance.', 'rrze-pieksy'));
        printf('<h3 style="margin-bottom: 0">%s</h3>', __('Availability Table', 'rrze-pieksy'));
        printf('<p><code>[pieksy-availability room="%s"]</code></p>', get_the_ID());
        printf('<p>%s</p>', __('Add <code>booking_link="true"</code> to link the available timeslots to the pre-filled booking form.', 'rrze-pieksy'));
        printf('<p>%s</p>', __('Add <code>days="20"</code> to overwrite the default number of days.', 'rrze-pieksy'));
    }

    public function archiveShowAllRooms($query) {
        if ( ! is_admin() && $query->is_main_query() ) {
            if ( is_post_type_archive( 'room' ) ) {
                $query->set('posts_per_page', -1 );
            }
        }
    }
}
