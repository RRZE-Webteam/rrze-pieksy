<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

class Tools
{

    public function __construct() {
        //
    }

    public function onLoaded() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_head', array( $this, 'create_seats_add_js' ) );
    }

    public function add_admin_menu() {
        $menu_id = add_management_page(
            _x( 'Create Seats', 'admin page title', 'rrze-pieksy' ),
            _x( 'Pieksy Create Seats', 'admin menu entry title', 'rrze-pieksy' ),
            'manage_options',
            'rrze-pieksy-tools',
            array( $this, 'admin_page' )
        );
    }

    function admin_page() {
        // Empty the form so it won't be submitted twice.
        $value_room = '';
        $value_num_rows = '';
        $value_num_seats = '';
        $value_schema_rows = '';
        $value_schema_seats = '';
        $value_prefix = '';
        $value_start_number = '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html_x( 'Create Seats', 'admin page title', 'rrze-pieksy' ) . '</h1>';

        if ( isset( $_GET['status']) && $_GET['status'] == 'submit') {
            /*
             * Submit Form, Create Seats
             */

            $errors = [];
            if ($_GET['room_id'] == '') {
                $errors[] = __('Please select a room.');
            }
            if (($_GET['number_rows'] == '')) {
                $errors[] = __('Please specify the number of rows.');
            }
            if (!isset($_GET['schema_rows'])) {
                $errors[] = __('Please specify the rows numbering system.');
            }
            if (($_GET['number_seats'] == '')) {
                $errors[] = __('Please specify the number of seats.');
            }
            if (!isset($_GET['schema_seats'])) {
                $errors[] = __('Please specify the seats numbering system.');
            }
            if (!empty($errors)) {
                echo '<div class="notice notice-error">';
                echo '<h2>' . __('An error occurred') . '</h2>';
                echo '<p>' . explode('<br />', $errors) . '</p>';
                echo '</div>';
            } else {
                $room_id = isset($_GET['room_id']) ? absint($_GET['room_id']) : '';
                $num_rows = isset($_GET['number_rows']) ? absint($_GET['number_rows']) : 0;
                $num_seats = isset($_GET['number_rows']) ? absint($_GET['number_seats']) : 0;
                $row = (isset($_GET['schema_rows']) && $_GET['schema_rows'] == 'a-z') ? 'A' : 1;
                $seat = (isset($_GET['schema_seats']) && $_GET['schema_seats'] == 'num') ? 1 : 'A';
                $seat_start = $seat;
                if ($row == $seat) {
                    $label_row = _x('Row', 'part of the generated seat name', 'rrze-pieksy') . ' ';
                    $label_seat = ' - ' . _x('Seat', 'part of the generated seat name', 'rrze-pieksy') . ' ';
                } else {
                    $label_row = '';
                    $label_seat = '';
                }
                if ($_GET['schema_rows'] == 'num' && isset($_GET['start_number']) && is_numeric($_GET['start_number'])) {
                    $row = absint($_GET['start_number']);
                }
                $prefix = isset($_GET['prefix']) ? sanitize_text_field($_GET['prefix']) . ' ' : '';
                $num_created = 0;

                for ($i = 1; $i <= $num_rows; $i++) {
                    for ($j = 1; $j <= $num_seats; $j++) {
                        $seat_name = $prefix . $label_row . $row . $label_seat . $seat;
                        $new_seat = [
                            'post_status' => 'publish',
                            'post_type' => 'seat',
                            'post_title' => $seat_name,
                        ];
                        $seat_id = wp_insert_post($new_seat);
                        $num_created++;
                        if ($seat_id) {
                            update_post_meta($seat_id, 'rrze-pieksy-seat-room', $room_id);
                        }
                        $seat++;
                    }
                    $row++;
                    $seat = $seat_start;
                }

                echo '<div class="notice notice-success is-dismissible">';
                echo '<h2>' . __('Done!', 'rrze-pieksy') . '</h2>';
                if ($num_created > 0) {
                    echo '<p>' . sprintf(_n('%s new seat has been created.', '%s new seats have been created.', $num_created, 'rrze-pieksy'), $num_created) . '</p>';
                }
                echo '</div>';
            }
        }

        /*
         * Build Form
         */

        echo '<form id="pieksy-create-seats" method="get">';
        echo '<input type="hidden" name="page" value="rrze-pieksy-tools">';
        echo '<input type="hidden" name="status" value="submit">';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="room_id">' . __('Room', 'rrze-pieksy') . '</label></th>';
        echo '<td>'
            . '<select id="room_id" name="room_id" required>'
            . '<option value="">&mdash; ' . __('Please select', 'rrze-pieksy') . ' &mdash;</option>';
        $rooms = get_posts([
            'post_type' => 'room',
            'post_status' => 'publish',
            'nopaging' => true,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        foreach ($rooms as $room) {
            echo '<option value="' . $room->ID . '"' . selected($room->ID, $value_room, false) . '>' . $room->post_title . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr>'
            . '<th scope="row"><label for="number_rows">' . __('Number of Rows', 'rrze-pieksy') . '</label></th>'
            . '<td><input type="number" id="number_rows" name="number_rows" min="1" required value="' . $value_num_rows . '"></td>'
            . '</tr>';

        echo '<tr>'
            . '<th scope="row"><label for="number_seats">' . __('Number of Seats per Row', 'rrze-pieksy') . '</label></th>'
            . '<td><input type="number" id="number_seats" name="number_seats" min="1" required value="' . $value_num_seats . '"></td>'
            . '</tr>';

        echo '<tr>'
            . '<th scope="row"><strong>' . __('Rows Numbering System', 'rrze-pieksy') . '</strong></th>'
            . '<td><input type="radio" id="schema_rows_numeric" name="schema_rows" value="num" required ' . checked($value_schema_rows, 'num', false) . '> <label for="schema_rows_numeric">' . __('Numeric (1 - 999)', 'rrze-pieksy') . '</label><br />'
            . '<input type="radio" id="schema_rows_a-z" name="schema_rows" value="a-z" ' . checked($value_schema_rows, 'a-z', false) . '> <label  for="schema_rows_a-z">' . __('A-Z', 'rrze-pieksy') . '</label>'
            . '</td>'
            . '</tr>';

        echo '<tr class="optional_start_number">'
            . '<th scope="row"><label for="start_number">' . __('Starting number', 'rrze-pieksy') . ' [' . __('optional', 'rrze-pieksy') . ']</label></th>'
            . '<td><input type="number" id="start_number" name="start_number" min="1" value="' . $value_start_number . '">'
            . '<p class="description">' . __('Row numbers will start with 1 by default. You may enter a different starting number, e.g. 101.', 'rrze-pieksy') . '</p>'
            . '</td>'
            . '</tr>';

        echo '<tr>'
            . '<th scope="row"><strong>' . __('Seats Numbering System', 'rrze-pieksy') . '</strong></th>'
            . '<td><input type="radio" id="schema_seats_numeric" name="schema_seats" value="num" required ' . checked($value_schema_seats, 'num', false) . '> <label  for="schema_seats_numeric">' . __('Numeric (1 - 999)', 'rrze-pieksy') . '</label><br />'
            . '<input type="radio" id="schema_seats_a-z" name="schema_seats" value="a-z" ' . checked($value_schema_seats, 'a-z', false) . '> <label  for="schema_seats_a-z">' . __('A-Z', 'rrze-pieksy') . '</label></td>'
            . '</tr>';

        echo '<tr>'
            . '<th scope="row"><label for="prefix">' . __('Prefix', 'rrze-pieksy') . ' [' . __('optional', 'rrze-pieksy') . ']</label></th>'
            . '<td><input type="text" id="prefix" name="prefix" value="' . $value_prefix . '">'
            . '<p class="description">' . __('Seat names will look like "15A" or "C7" where the first element indicates the row and the second indicates the seat. <br />If the same numbering system ist used for rows and seats, labels will be added automatically: "Row 20 - Seat 3".<br />If a name prefix is given, it will be added before the row, e.g. "1st floor 15A"', 'rrze-pieksy') . '</p>'
            . '</td>'
            . '</tr>';

        echo '</tbody></table>';
        echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Create Seats', 'rrze-pieksy') . '"></p>';

        echo '</form>';
        echo '</div>';
    }

    public function create_seats_add_js() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($){
                var form = $('#pieksy-create-seats'),
                    optional = form.find('tr.optional_start_number');
                if (optional.find('input#start_number').val() < 1) {
                    optional.hide();
                }
                form.find('input[name="schema_rows"]').change(function() {
                    if ( $(this).val() == 'num') {
                        optional.fadeIn();
                    }  else {
                        optional.find('input#start_number').val('');
                        optional.hide();

                    }
                });
            });
        </script>
        <?php
    }
}