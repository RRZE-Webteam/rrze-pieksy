<?php

namespace RRZE\RSVP;

defined('ABSPATH') || exit;

use RRZE\RSVP\Settings;

class Tracking {
    const DB_TABLE = 'rrze_rsvp_tracking';

    const DB_VERSION = '1.0.0';
    const DB_VERSION_OPTION_NAME = 'rrze_rsvp_tracking_db_version';

    protected $settings;
    protected $trackingTable;
    protected $dbVersion;
    protected $dbOptionName;
    protected $contact_tracking_note;


    public function __construct() {
        $this->dbVersion = static::DB_VERSION;
        $this->dbOptionName = static::DB_VERSION_OPTION_NAME;
        $this->settings = new Settings(plugin()->getFile());
        $this->contact_tracking_note = $this->settings->getOption('general', 'contact_tracking_note');
    }


    public function onLoaded() {
        // Klärungsbedarf: 
        // WARNING AUSGEBEN wenn "einfach mal so" Plugin de- und aktiviert wird
        // wird Plugin beim Autoupdate über die Netzwerkeinstellungen / Repo auch de- / aktiviert?  
        // Ebenfalls warnen, wenn rrze-rsvp-network de- und aktiviert wird
        // was soll beim De- und Aktivieren von rrze-rsvp passieren? Daten aus network-table löschen? nicht löschen? Tables droppen? nicht droppen? 
        // VORSICHT wg Mischzustand / Datenkonsistenz!
        // => BK 2DO: Konstellationen durchtesten

        // use cases defined in https://github.com/RRZE-Webteam/rrze-rsvp/issues/110

        if (is_multisite()){
            if (is_plugin_active_for_network( 'rrze-rsvp-network/rrze-rsvp-network.php' )){
                // use case C "Multisite: mit rrze-rsvp-network":
                // Admin darf CSV NICHT erstellen
                // SuperAdmin erstellt CSV über Menüpunkt in Network-Dashboard
                $this->createTrackingTable('network');

                // check if local tracking table for this blog already exists (in this version: we do not drop tables on plugin deactivation + we ignore garbage collecting plugins which do drop those tables)
                $blogID = get_current_blog_id();
                $checkTable = Tracking::getTrackingTableName('local', $blogID);
                if ($this->checkTableExists($checkTable)){
                    // use case B "Multisite: rrze-rsvp-network wird NACH rrze-rsvp aktiviert":
                    // Admins dürfen CSV NICHT MEHR erstellen
                    // SuperAdmin erstellt CSV über Menüpunkt in Network-Dashboard
                    // => Merge aller lokalen Tracking-Tabellen in zentrale Tracking-Tabelle
                    // => lokale Tracking-Tabellen werden gelöscht
                    // $this->createTrackingTable('network');

                    $blogIDs = $this->getBlogIDs();

                    foreach ($blogIDs as $blogID) {
                        if (false !== $this->fillTrackingTable('network', $blogID)){
                            $this->dropTrackingTable('local', $blogID);
                        }else{
                            // exception handling
                        }
                    }
                }
            }else{
                // check if network tracking table already exists (in this version: we do not drop tables on plugin deactivation + we ignore garbage collecting plugins which do drop those tables)
                $checkTable = Tracking::getTrackingTableName('network');
                if ($this->checkTableExists($checkTable)){
                    // use case D "Multisite: rrze-rsvp-network wird DEAKTIVIERT":
                    // Admin darf CSV (wieder) erstellen
                    // => Lokale Tracking-Tabelle wird erstellt und mit den Daten von zentraler Tracking-Tabelle gefüllt, die zu dieser Website gehören
                    // => Zentrale Tracking-Tabelle wird gelöscht
                    $this->createTrackingTable('local');
                    if (false !== $this->fillTrackingTable('local')){
                        $this->dropTrackingTable('network');
                    }else{
                        // exception handling
                    }
                }else{
                    // use case A "Multisite: ohne rrze-rsvp-network":
                    // Admin darf CSV erstellen
                    // => Tracking-Tabelle PRO WEBSITE (nicht als zentrale Tracking-Tabelle wie in [1] umgesetzt)
                    $this->createTrackingTable('local');
                }
            }
            add_action( 'admin_menu', [$this, 'add_tracking_menuinfo'] );
        }else{
            // use cases E "Singlesite":
            $this->createTrackingTable('local');

            add_action( 'admin_menu', [$this, 'add_tracking_menu'] );
            add_action( 'wp_ajax_csv_pull', [$this, 'tracking_csv_pull'] );
        }
        add_action('rrze-rsvp-checked-in', [$this, 'insertTracking'], 10, 2);
    }
    
 
    protected function isUpdate() {
        // BK 2DO: get_site_option & set_site_option seems to be quite useless if there is no "create OR REPLACE table" (which is "DROP TABLE IF EXISTS `tablename`; CREATE TABLE..." in MySQL) ... combined with caching old data to insert into new table (CREATE TEMPORARY TABLE ... ) but this could be an overkill
        if (get_site_option($this->dbOptionName, NULL) != $this->dbVersion) {
            update_site_option($this->dbOptionName, $this->dbVersion);
            return true;
        }
        return false;
    }


    protected function getBlogIDs(){
        // parameters copied from plugin rrze-netzwerk-audit (10000 blogs is the limit)
        return get_sites(
            [
                'number' => 10000,
                'network_id' => get_current_network_id(),
                'archived' => 0,
                'mature' => 0,
                'spam' => 0,
                'deleted' => 0,
                'fields' => 'ids'
            ]
        );
    }


    public function add_tracking_menu() {
        $menu_id = add_management_page(
            _x( 'Contact tracking', 'admin page title', 'rrze-rsvp' ),
            _x( 'RSVP Contact tracking', 'admin menu entry title', 'rrze-rsvp' ),
            'manage_options',
            'rrze-rsvp-tracking',
            [$this, 'admin_page_tracking']
        );
    }


    public function add_tracking_menuinfo() {
        $menu_id = add_management_page(
            _x( 'Contact tracking', 'admin page title', 'rrze-rsvp' ),
            _x( 'RSVP Contact tracking', 'admin menu entry title', 'rrze-rsvp' ),
            'manage_options',
            'rrze-rsvp-tracking',
            [$this, 'admin_page_trackinginfo']
        );
    }


    public function admin_page_tracking() {
        $searchdate = '';
        $delta = 0;
        $guest_firstname = '';
        $guest_lastname = '';
        $guest_email = '';
        $guest_phone = '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html_x( 'Contact tracking', 'admin page title', 'rrze-rsvp' ) . '</h1>';

        if ( isset( $_GET['submit'])) {
            $searchdate = filter_input(INPUT_GET, 'searchdate', FILTER_SANITIZE_STRING); // filter stimmt nicht
            $delta = filter_input(INPUT_GET, 'delta', FILTER_VALIDATE_INT, ['min_range' => 0]);
            $guest_firstname = filter_input(INPUT_GET, 'guest_firstname', FILTER_SANITIZE_STRING);
            $guest_lastname = filter_input(INPUT_GET, 'guest_lastname', FILTER_SANITIZE_STRING);
            $guest_email = filter_input(INPUT_GET, 'guest_email', FILTER_VALIDATE_EMAIL);
            $guest_phone = filter_input(INPUT_GET, 'guest_phone', FILTER_SANITIZE_STRING);

            $aGuests = Tracking::getUsersInRoomAtDate($searchdate, $delta, $guest_firstname, $guest_lastname, $guest_email, $guest_phone);

            if ($aGuests){
                $ajax_url = admin_url('admin-ajax.php?action=csv_pull') . '&page=rrze-rsvp-tracking&searchdate=' . urlencode($searchdate) . '&delta=' . urlencode($delta) . '&guest_firstname=' . urlencode($guest_firstname) . '&guest_lastname=' . urlencode($guest_lastname) . '&guest_email=' . urlencode($guest_email) . '&guest_phone=' . urlencode($guest_phone);
                echo '<div class="notice notice-success is-dismissible">'
                    . '<h2>Guests found!</h2>'
                    . "<a href='$ajax_url'>Download CSV</a>"
                    . '</div>';
            }else{
                echo '<div class="notice notice-success is-dismissible">'
                    . '<h2>No guests found</h2>'
                    . '</div>';
            }
        }

        echo '<form id="rsvp-search-tracking" method="get">';
        echo '<input type="hidden" name="page" value="rrze-rsvp-tracking">';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>'
            . '<th scope="row"><label for="searchdate">' . __('Search date', 'rrze-rsvp') . '</label></th>'
            . '<td><input type="text" id="searchdate" name="searchdate" placeholder="YYYY-MM-DD" pattern="(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))" value="' . $searchdate . '">'
            . '</td>'
            . '</tr>';
        echo '<tr>'
            . '<th scope="row"><label for="delta">' . '&#177; ' . __('days', 'rrze-rsvp') . '</label></th>'
            . '<td><input type="number" id="delta" name="delta" min="0" required value="' . $delta . '"></td>'
            . '</tr>';
        echo '<tr>'
            . '<th scope="row"><label for="guest_firstname">' . __('First name', 'rrze-rsvp') . '</label></th>'
            . '<td><input type="text" id="guest_firstname" name="guest_firstname" value="' . $guest_firstname . '">'
            . '</td>'
            . '</tr>';
        echo '<tr>'
            . '<th scope="row"><label for="guest_lastname">' . __('Last name', 'rrze-rsvp') . '</label></th>'
            . '<td><input type="text" id="guest_lastname" name="guest_lastname" value="' . $guest_lastname . '">'
            . '</td>'
            . '</tr>';
        echo '<tr>'
            . '<th scope="row"><label for="guest_email">' . __('Email', 'rrze-rsvp') . '</label></th>'
            . '<td><input type="text" id="guest_email" name="guest_email" value="' . $guest_email . '">'
            . '</td>'
            . '</tr>';
        echo '<tr>'
            . '<th scope="row"><label for="guest_phone">' . __('Phone', 'rrze-rsvp') . '</label></th>'
            . '<td><input type="text" id="guest_phone" name="guest_phone" value="' . $guest_phone . '">'
            . '</td>'
            . '</tr>';
        echo '</tbody></table>';
        echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Search', 'rrze-rsvp') . '"></p>';

        echo '</form>';
        echo '</div>';
    }


    public function admin_page_trackinginfo() {
        echo '<div class="wrap">'
            . '<h1>' . esc_html_x( 'Contact tracking', 'admin page title', 'rrze-rsvp' ) . '</h1>'
            . '<span class="rrze-rsvp-tracking-info">' . $this->contact_tracking_note . '</span>'
            . '</div>';
    }


    public function tracking_csv_pull() {
        $searchdate = filter_input(INPUT_GET, 'searchdate', FILTER_SANITIZE_STRING); // filter stimmt nicht
        $delta = filter_input(INPUT_GET, 'delta', FILTER_VALIDATE_INT, ['min_range' => 0]);
        $guest_firstname = filter_input(INPUT_GET, 'guest_firstname', FILTER_SANITIZE_STRING);
        $guest_lastname = filter_input(INPUT_GET, 'guest_lastname', FILTER_SANITIZE_STRING);
        $guest_email = filter_input(INPUT_GET, 'guest_email', FILTER_VALIDATE_EMAIL);
        $guest_phone = filter_input(INPUT_GET, 'guest_phone', FILTER_SANITIZE_STRING);

        $aGuests = Tracking::getUsersInRoomAtDate($searchdate, $delta, $guest_firstname, $guest_lastname, $guest_email, $guest_phone);

        $file = 'rrze_tracking_csv';
        $csv_output = 'START,END,ROOM,STREET,ZIP,CITY,EMAIL,PHONE,FIRSTNAME,LASTNAME'."\n";

        if ($aGuests){
            foreach ($aGuests as $row){
                $row = array_values($row);
                $row = implode(",", $row);
                $csv_output .= $row."\n";
             }
        }
 
        $filename = $file . "_" . date("Y-m-d_H-i", time());
        header( "Content-type: application/vnd.ms-excel" );
        header( "Content-disposition: csv" . date("Y-m-d") . ".csv" );
        header( "Content-disposition: filename=" . $filename . ".csv" );
        print $csv_output;
        exit;
    }


    protected function fillTrackingTable(string $into, int $blogID = 0){
        global $wpdb;
        $blogID = $blogID ? $blogID : get_current_blog_id();

        $from = ($into == 'network' ? 'local' : 'network'); 
        $fromTable = Tracking::getTrackingTableName($from);
        $intoTable = Tracking::getTrackingTableName($into);

        $prepare_vals = [
            $into,
            $from,
            $blogID
        ];

        return $wpdb->query( 
               $wpdb->prepare("INSERT INTO {$intoTable} (blog_id, start, end, room_post_id, room_name, room_street, room_zip, room_city, seat_name, hash_seat_name, guest_firstname, guest_lastname, hash_guest_firstname, hash_guest_lastname, guest_email, hash_guest_email, guest_phone, hash_guest_phone) 
                    SELECT blog_id, start, end, room_post_id, room_name, room_street, room_zip, room_city, seat_name, hash_seat_name, guest_firstname, guest_lastname, hash_guest_firstname, hash_guest_lastname, guest_email, hash_guest_email, guest_phone, hash_guest_phone
                    FROM {$fromTable} WHERE  blog_id = {$blogID}", $prepare_vals)); // returns 1, 0 or false
    }

    protected function dropTrackingTable(string $tableType, int $blogID = 0): bool{
        global $wpdb;
        $blogID = $blogID ? $blogID : get_current_blog_id();
        $dropTable = Tracking::getTrackingTableName($tableType, $blogID);

        return $wpdb->query(
               $wpdb->prepare("DROP TABLE IF EXISTS {$dropTable}", $dropTable)); // returns true/false
    }


    public function insertTracking(int $blogID, int $bookingId) {
        // Note: insertTracking() is called via action hook 'rrze-rsvp-checked-in' 
        //       see $this->onLoaded : add_action('rrze-rsvp-checked-in', [$this, 'insertTracking'], 10, 2);
        //       see Actions.php : do_action('rrze-rsvp-checked-in', get_current_blog_id(), $bookingId);

        global $wpdb;

        $booking = Functions::getBooking($bookingId);
        if (!$booking) {
            return;
        }

        $start = date('Y-m-d H:i:s', get_post_meta($bookingId, 'rrze-rsvp-booking-start', true));
        $end = date('Y-m-d H:i:s', get_post_meta($bookingId, 'rrze-rsvp-booking-end', true));

        $fields = [
            'blog_id' => $blogID,
            'start' => $start,
            'end' => $end,
            'room_post_id' => (int)$booking['room'],
            'room_name' => $booking['room_name'],
            'room_street' => $booking['room_street'],
            'room_zip' => (int)$booking['room_zip'],
            'room_city' => $booking['room_city'],
            'seat_name' => $booking['seat_name'],
            'hash_seat_name' => Functions::crypt(strtolower($booking['seat_name'])),
            'guest_firstname' => $booking['guest_firstname'],
            'hash_guest_firstname' => Functions::crypt(strtolower($booking['guest_firstname'])),
            'guest_lastname' => $booking['guest_lastname'],
            'hash_guest_lastname' => Functions::crypt(strtolower($booking['guest_lastname'])),
            'guest_email' => $booking['guest_email'],
            'hash_guest_email' => Functions::crypt(strtolower($booking['guest_email'])),
            'guest_phone' => $booking['guest_phone'],
            'hash_guest_phone' => Functions::crypt($booking['guest_phone']),
        ];

        $wpdb->insert(
            $this->trackingTable,
            $fields,
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        return $wpdb->insert_id; // returns the id (AUTO_INCREMENT) or false
    }



    public static function getUsersInRoomAtDate(string $searchdate, int $delta, string $guest_firstname, string $guest_lastname, string $guest_email = '', string $guest_phone = ''): array {
        global $wpdb;

        if (!$guest_email && !$guest_firstname && !$guest_lastname){
            // we have nothing to search for
            return [];
        }

        if (!Functions::validateDate($searchdate)){
            // is not 'YYYY-MM-DD'
            return [];
        }

        $tableType = get_option('rsvp_tracking_tabletype');
        $trackingTable = Tracking::getTrackingTableName($tableType);

        //  "Identifikationsmerkmalen für eine Person (Name, E-Mail und oder Telefon)" see https://github.com/RRZE-Webteam/rrze-rsvp/issues/89
        $hash_guest_firstname = Functions::crypt(strtolower($guest_firstname));
        $hash_guest_lastname = Functions::crypt(strtolower($guest_lastname));
        $hash_guest_email = Functions::crypt(strtolower($guest_email));
        $hash_guest_phone = Functions::crypt($guest_phone);

        $prepare_vals = [
            $searchdate,
            $delta,
            $searchdate,
            $delta,
            $searchdate,
            $delta,
            $searchdate,
            $delta,
            $searchdate,
            $delta,
            $searchdate,
            $delta,
            $searchdate,
            $delta,
            $searchdate,
            $delta,
            $hash_guest_firstname,
            $hash_guest_lastname,
            $hash_guest_email,
            $hash_guest_phone
        ];

        // simpelst solution would be: 
        // select ... INTO OUTFILE '$path_to_file' FIELDS TERMINATED BY ',' LINES TERMINATED BY ';' from ...
        // but this is a question of user's file writing rights
        return $wpdb->get_results( 
            $wpdb->prepare("SELECT surrounds.start, surrounds.end, surrounds.room_name, surrounds.room_street, surrounds.room_zip, surrounds.room_city, surrounds.guest_email, surrounds.guest_phone, surrounds.guest_firstname, surrounds.guest_lastname 
            FROM {$dbTrackingTable} AS surrounds 
            WHERE (DATE(surrounds.start) BETWEEN DATE_SUB(%s, INTERVAL %d DAY) AND DATE_ADD(%s, INTERVAL %d DAY)) AND (DATE(surrounds.end) BETWEEN DATE_SUB(%s, INTERVAL %d DAY) AND DATE_ADD(%s, INTERVAL %d DAY)) AND 
            surrounds.room_post_id IN 
            (SELECT needle.room_post_id FROM {$dbTrackingTable} AS needle WHERE 
            (DATE(needle.start) BETWEEN DATE_SUB(%s, INTERVAL %d DAY) AND DATE_ADD(%s, INTERVAL %d DAY)) AND 
            (DATE(needle.end) BETWEEN DATE_SUB(%s, INTERVAL %d DAY) AND DATE_ADD(%s, INTERVAL %d DAY)) AND 
            needle.hash_guest_firstname = %s AND needle.hash_guest_lastname = %s AND
            ((needle.hash_guest_email = %s) OR (needle.hash_guest_phone = %s))) 
            ORDER BY surrounds.start, surrounds.guest_lastname", $prepare_vals), ARRAY_A); // returns assoc array
    }


    protected function createTrackingTable( string $tableType = 'network' ) {
        global $wpdb;

        // store $tableType we are using for this blog (because of the use cases defined in https://github.com/RRZE-Webteam/rrze-rsvp/issues/110)
        update_option('rsvp_tracking_tabletype', $tableType, false);

        if (!$this->isUpdate()){
            return;
        }

        $trackingTable = Tracking::getTrackingTableName($tableType);
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . $trackingTable . " (
            id bigint(20) UNSIGNED NOT NULL auto_increment,
            blog_id bigint(20) NOT NULL,
            ts_updated timestamp DEFAULT CURRENT_TIMESTAMP,
            ts_inserted timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            start datetime NOT NULL,
            end datetime NOT NULL,
            room_post_id bigint(20) NOT NULL,
            room_name text NOT NULL,
            room_street text NOT NULL, 
            room_zip varchar(10) NOT NULL,
            room_city text NOT NULL, 
            seat_name text NOT NULL, 
            hash_seat_name char(64) NOT NULL,
            guest_firstname text NOT NULL, 
            guest_lastname text NOT NULL, 
            hash_guest_firstname char(64) NOT NULL,
            hash_guest_lastname char(64) NOT NULL,
            guest_email text NOT NULL, 
            hash_guest_email char(64) NOT NULL,
            guest_phone text NOT NULL, 
            hash_guest_phone char(64) NOT NULL,
            PRIMARY KEY  (id),
            KEY k_blog_id (blog_id)
            ) $charsetCollate;";
            // reason for all those hashes is that you cannot use TEXT (but CHAR / VARCHAR) for any kind of index resp KEY which improves performance & data integrity big times :-D 
            // ,UNIQUE KEY uk_guest_room_time (start,end,room_post_id,hash_seat_name,hash_guest_firstname,hash_guest_lastname,hash_guest_email,hash_guest_phone)            

        echo "<script>console.log('sql = " . $sql . "' );</script>";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // echo '<pre>';
        // var_dump($sql);
        // exit;

        $aRet = dbDelta($sql);
        // echo "<script>console.log('dbDelta returns " . json_encode($aRet) . "' );</script>";
    }

    public static function getTrackingTableName( string $tableType = 'network', int $blogID = 0 ): string {
        global $wpdb;
        return ( $tableType == 'network' ? $wpdb->base_prefix : $wpdb->get_blog_prefix($blogID) ) . static::DB_TABLE;
    }


    protected function checkTableExists($tableName){
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}' AND table_name = '{$tableName}' LIMIT 1", ARRAY_A);
    }

}
