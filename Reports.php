<?php
/**
 * Description of Reports
 *
 * @author jeff
 */
class BMReports {
    
    private $db;
    
    private $getVars;
    
    private $postVars;
    
    private $adminUrl;
    
    private $expireDateClause;
    
    private $headerArr;
    
    private $exportHeaderArr;
    
    private $memLevelText = 'Memberships with Level';
    
    private $memSignupText = 'Members by Signup Date';
    
    private $currentUserText = 'Current Users';
    
    private $premiumMembersText = 'Premium Members';
    
    private $promoMembersText = 'Promotional Code Members';
    
    private $nonMembersText = 'All Non-Members';
    
    private $autoRenewMembersText = 'Auto Renewal Members';
    
    private $nhcpinText = 'Archived NHC PINs';
    
    private $profileUrl;
    
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        
        $this->getVars = $_GET;
        
        $this->postVars = $_POST;
        
        $this->adminUrl = admin_url().'REDACTED.php?page=bm-ntra-reports';
        
        $this->profileUrl = get_site_url();
        $this->profileUrl .= '/ntra-member-profile/?ntraid=';

        /* Use this at the beginning of the year until NTRA is ready to look
         * only at the current (new) year.
         */
        $useFuzzyDates = $this->getNHCMetaData('use_fuzzy_expire_date');
        $this->setExpireDate($useFuzzyDates);
    }
    
    
    
    
    public function router() {
        echo '<br /><h1>NTRA Member Reports</h1>';
        
        $this->showReportOptions();
        
        if(isset($this->getVars['report'])) {
            switch($this->getVars['report']) {
                case 'level':
                    $this->showMemberLevel();
                    break;
                case 'member':
                    $this->showCurrentUsers();
                    break;
                case 'premium':
                    $this->showPremiumMembers();
                    break;
                case 'promo':
                    $this->showPromoMembers();
                    break;
                case 'memsignup':
                    $this->showBySignup();
                    break;
                case 'nonmember':
                    $this->showNonMembers();
                    break;
                case 'autorenew':
                    $this->showAutoRenewMembers();
                    break;
                case 'nhcpin':
                    $this->showMembersNHCPIN();
                    break;
                case 'runscript':
                    $this->runScript();
                    break;
            }
        }
        elseif(isset($this->postVars['memberSearch'])) {
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'searchMembers')) {
                echo 'There was a problem processing your submission.';
                $this->showReportOptions();
                exit;
            }
            $this->searchMembers();
        }
    }
    
    
    
    private function showReportOptions() {
        $searchVal = '';
        if(isset($this->postVars['memberSearch'])) {
            $searchVal = $this->postVars['memberSearch'];
        }
        echo '
            <h4><a href="'.$this->adminUrl.'&report=level">View '.$this->memLevelText.'</a></h4>
            <h4><a href="'.$this->adminUrl.'&report=member">View '.$this->currentUserText.'</a></h4>
            <h4><a href="'.$this->adminUrl.'&report=memsignup">View '.$this->memSignupText.'</a></h4>
            <h4><a href="'.$this->adminUrl.'&report=premium">View '.$this->premiumMembersText.'</a></h4>
            <h4><a href="'.$this->adminUrl.'&report=promo">View '.$this->promoMembersText.'</a></h4>
            <h4><a href="'.$this->adminUrl.'&report=autorenew">View '.$this->autoRenewMembersText.'</a></h4>
            <h4><a href="'.$this->adminUrl.'&report=nonmember">View '.$this->nonMembersText.'</a></h4>
            <h4><a href="'.$this->adminUrl.'&report=nhcpin">View '.$this->nhcpinText.'</a></h4>
            <br />
            <h2>Search Members</h2>
            <p>Search for name, email or NHC PIN.</p>
            <form method="post" action="'.$this->adminUrl.'">
                <input name="memberSearch" type="text" size="30" value="'.$searchVal.'" />
                &nbsp; <input type="submit" name="submit" value="Search" />';
        
        wp_nonce_field('searchMembers');
        
        echo '  
            </form>';
        
        /** JM - only add this into the mix for special one-off processes
        echo '
        <h4><a href="'.$this->adminUrl.'&report=runscript">Do Not Click! Admin script by Jeff M</a></h4>';
         /* 
         */
    }
    
    
    
    private function showMemberLevel() {
        $query = "SELECT DISTINCT  u.id, n.nhc_pin, um1.meta_value AS first_name, um2.meta_value AS last_name, u.user_email, um3.meta_value AS level
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    JOIN {$this->db->prefix}usermeta um3 ON um3.user_id = u.id AND um3.meta_key = 'member_level' AND um3.meta_value <> 'free'
                    JOIN {$this->db->prefix}usermeta um4 ON um4.user_id = u.id AND um4.meta_key = 'expiration_date' AND um4.meta_value {$this->expireDateClause}
                    ORDER BY n.nhc_pin";

        $this->showResults($query, 'level', true);
    }
    
    
    private function showMembersNHCPIN() {
        $query = "SELECT DISTINCT  n.nhc_pin, n.year, u.user_email, um1.meta_value AS first_name, um2.meta_value AS last_name, u.id AS uid
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc_bak n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    ORDER BY n.nhc_pin";
        
        $this->showResults($query, 'nhcpin', false);
    }
    
    
    private function showBySignup() {
        $query = "SELECT DISTINCT  u.id, n.nhc_pin, u.user_login, u.user_email, um3.meta_value AS signup_date, um1.meta_value AS first_name, um2.meta_value AS last_name
                    FROM {$this->db->prefix}users u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    JOIN {$this->db->prefix}usermeta um3 ON um3.user_id = u.id AND um3.meta_key = 'nhc_signup_date'
                    JOIN {$this->db->prefix}usermeta um4 ON um4.user_id = u.id AND um4.meta_key = 'expiration_date' AND um4.meta_value {$this->expireDateClause}
                    ORDER BY um3.meta_value DESC";
        
        $membersArr = $this->db->get_results($query, ARRAY_A);
        
        foreach($membersArr as $key => $m) {
            $tmp = get_user_meta($m['id']);
            $membersArr[$key]['addr1'] = $tmp['addr1'][0];
            $membersArr[$key]['addr2'] = $tmp['addr2'][0];
            $membersArr[$key]['city'] = $tmp['city'][0];
            $membersArr[$key]['thestate'] = $tmp['thestate'][0];
            $membersArr[$key]['zip'] = $tmp['zip'][0];
            $membersArr[$key]['country'] = $tmp['country'][0];
            $membersArr[$key]['level'] = $tmp['member_level'][0];
        }
        
        $this->showResults($membersArr, 'bysignup', true);
    }
    
    
    private function showCurrentUsers() {
        $query = "SELECT DISTINCT  u.id, n.nhc_pin, um1.meta_value AS first_name, um2.meta_value AS last_name, u.user_email
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    JOIN {$this->db->prefix}usermeta um4 ON um4.user_id = u.id AND um4.meta_key = 'expiration_date' AND um4.meta_value {$this->expireDateClause}
                    ORDER BY n.nhc_pin";
        
        $this->showResults($query, 'current');
    }
    
    
    private function showAutoRenewMembers() {
        $query = "SELECT DISTINCT  u.id, n.nhc_pin, um1.meta_value AS first_name, um2.meta_value AS last_name, um.meta_value AS auto_renew, u.user_email
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um ON um.user_id = u.id AND um.meta_key = 'auto_renew'
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    JOIN {$this->db->prefix}usermeta um4 ON um4.user_id = u.id AND um4.meta_key = 'expiration_date' AND um4.meta_value {$this->expireDateClause}
                    WHERE um.meta_value = 1
                    ORDER BY n.nhc_pin";
        
        $this->showResults($query, 'autorenew');
    }
    
    
    /**
     * This had to be done differently, as it would exceed the max limit of joins
     * to get all of the metadata info.
     */
    private function showPremiumMembers() {
        $query = "SELECT DISTINCT  u.id, n.nhc_pin, u.user_login, um1.meta_value AS first_name, um2.meta_value AS last_name, u.user_email
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um ON um.user_id = u.id AND um.meta_key = 'member_level' AND um.meta_value IN ('paid-75', 'paid-95')
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    JOIN {$this->db->prefix}usermeta um7 ON um7.user_id = u.id AND um7.meta_key = 'expiration_date' AND um7.meta_value {$this->expireDateClause}
                    ORDER BY um.meta_value";
        
        $membersArr = $this->db->get_results($query, ARRAY_A);
        
        foreach($membersArr as $key => $m) {
            $tmp = get_user_meta($m['id']);
            $membersArr[$key]['addr1'] = $tmp['addr1'][0];
            $membersArr[$key]['addr2'] = $tmp['addr2'][0];
            $membersArr[$key]['city'] = $tmp['city'][0];
            $membersArr[$key]['thestate'] = $tmp['thestate'][0];
            $membersArr[$key]['zip'] = $tmp['zip'][0];
            $membersArr[$key]['country'] = $tmp['country'][0];
            $membersArr[$key]['level'] = $tmp['member_level'][0];
        }
        
        $this->showResults($membersArr, 'premium', true);
    }
    
    private function showPromoMembers() {
        $query = "SELECT DISTINCT  u.id, n.nhc_pin, um1.meta_value AS first_name, um2.meta_value AS last_name, um.meta_value AS promo_code, u.user_email, um3.meta_value AS level
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um ON um.user_id = u.id AND um.meta_key = 'promo_code'
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    JOIN {$this->db->prefix}usermeta um3 ON um3.user_id = u.id AND um3.meta_key = 'member_level'
                    JOIN {$this->db->prefix}usermeta um4 ON um4.user_id = u.id AND um4.meta_key = 'expiration_date' AND um4.meta_value {$this->expireDateClause}
                    ORDER BY n.nhc_pin";
        
        $this->showResults($query, 'promo', true);
    }
    
    
    private function showNonMembers() {
        $tourYear = $this->getNHCMetaData('current_tournament_year');
        $query = "SELECT DISTINCT u.id, n.nhc_pin, um1.meta_value AS first_name, um2.meta_value AS last_name, u.user_email
                    FROM `{$this->db->prefix}users` u
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    JOIN {$this->db->prefix}usermeta um3 ON um3.user_id = u.id AND um3.meta_key = 'member_level'
                    LEFT JOIN {$this->db->prefix}nhc_bak n ON u.id = n.user_id 
                    WHERE um3.meta_value = 'free'";
        
        $this->showResults($query, 'nonmember');
    }
    
    
    private function searchMembers() {
        // if there's a space (like a user's full name) split it up to check it.
        $searchStr = $this->postVars['memberSearch'];
        $searchStrArr = array();
        if(preg_match('/\s/',$searchStr)) {
            $searchStrArr = explode(" ", $searchStr);
        }
        
        if(empty($searchStrArr)) {
            $query = "SELECT u.id
                    FROM {$this->db->prefix}users AS u
                    WHERE u.user_email = '$searchStr'
                    UNION
                    SELECT u.id
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id AND n.nhc_pin = '$searchStr'
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    UNION
                    SELECT u.id
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name' AND um1.meta_value like '%$searchStr%'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    UNION
                    SELECT u.id
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name' AND um2.meta_value like '%$searchStr%'";
                    //JOIN {$this->db->prefix}usermeta um4 ON um4.user_id = u.id AND um4.meta_key = 'expiration_date' AND um4.meta_value = '$expireDate'";
        }
        else { // looking specifically for a full name
            $query = "SELECT u.id
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name' AND um1.meta_value like '%{$searchStrArr[0]}%'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name' AND um2.meta_value like '%{$searchStrArr[1]}%'";
                    
        }
        
        $membersArr = $this->db->get_results($query, ARRAY_A);
        
        // filter through to weed out any duplicates
        $showArr = array();
        foreach($membersArr as $member) {
            if(!in_array($member['id'], $showArr)) {
                $showArr[] = $member['id'];
            }
        }
        $idStr = implode(",", $showArr);
        
        $query = "SELECT DISTINCT  u.id, n.nhc_pin, um1.meta_value AS first_name, um2.meta_value AS last_name, u.user_email, um3.meta_value AS level
                    FROM {$this->db->prefix}users AS u
                    JOIN {$this->db->prefix}nhc n ON u.id = n.user_id
                    JOIN {$this->db->prefix}usermeta um1 ON um1.user_id = u.id AND um1.meta_key = 'first_name'
                    JOIN {$this->db->prefix}usermeta um2 ON um2.user_id = u.id AND um2.meta_key = 'last_name'
                    JOIN {$this->db->prefix}usermeta um3 ON um3.user_id = u.id AND um3.meta_key = 'member_level'
                    WHERE u.id IN ($idStr)
                    ORDER BY n.nhc_pin";
        
        $this->showResults($query, 'search', true);
    }
    



    private function setExpireDate($useFuzzyDates) {
        $naturalExpire = $this->getNHCMetaData('current_membership_expire');
        if($useFuzzyDates == 1) {
            $year = intval(substr($naturalExpire, 0, 4));
            //$year -= 2;
            $this->expireDateClause = "< '$year-1-20 00:00:00'";
        }
        else {
            $this->expireDateClause = "= '$naturalExpire'";
        }
    }




    
    private function showResults($query, $type, $showLevel=false) {
        if(is_array($query)) {
            $membersArr = $query;
        }
        else {
            $membersArr = $this->db->get_results($query, ARRAY_A);
        }
        
        $this->setTableHeaders($type, $showLevel);
        
        if($membersArr) {
            if(isset($this->getVars['export'])) {
                $this->export($membersArr);
            }
            else {
                $this->showTable($membersArr, $this->currentUserText, $type, $showLevel);
            }
        }
        else {
            echo 'No results';
        }
        
    }
    
    
    
    
    
    private function showTable($membersArr, $title, $type, $showLevel=false) {
        $exportLink = '';
        if(isset($this->getVars['report'])) {
            $exportLink = '<div><a href="'.$this->adminUrl.'&report='.$this->getVars['report'].'&export=csv">Export to Excel</a></div>';
        }
        $output = '
                <h2>'.$title.'</h2>
                '.$exportLink.'
                <div class="table-2">
                    <table width="75%">
                        <thead>
                            <tr>';
        foreach($this->headerArr as $h) {
            $output .= '
                                <th align="left">'.$h.'</th>';
        }
        
        $output .= '
                        </thead>
                        <tbody>';
        
        
        foreach($membersArr as $mem) {
            // get profile link
            $encoded = encodePlayerPin(array('id' => $mem['id']));
            $encodedLink = $this->profileUrl.$encoded;
        
            $output .= '
                            <tr>';
            
            if(isset($mem['nhc_pin']) && $mem['nhc_pin'] > 0) {
                $output .= '
                                <td>'.$mem['nhc_pin'].'</td>';
            }
            else {
                $output .= '
                                <td>&nbsp;</td>';
            }
            
            switch($type) {
                case 'premium':
                case 'bysignup':
                    $signupDate = ''; // default to prevent notices in the logs
                    if(isset($mem['signup_date'])) {
                        $theDate = new DateTime($mem['signup_date']);
                        $signupDate = $theDate->format('m/d/Y g:i:s a');
                    }
                    $theAddress = $mem['addr1'];
                    if(!empty($mem['addr2'])) {
                        $theAddress .= '<br />'.$mem['addr2'];
                    }
                    $theAddress .= '<br />'.$mem['city'].', '.$mem['thestate'].' '.$mem['zip'].' '.$mem['country'];
                    
                    $output .= '
                                <td><a href="'.$encodedLink.'" target="_blank">'.$mem['user_login'].'</a></td>
                                <td>'.$signupDate.'</td>
                                <td>'.$mem['first_name'].' '.$mem['last_name'].'</td>
                                <td>'.$theAddress.'</td>';
                    break;
                case 'promo':
                    $output .= '
                                <td><a href="'.$encodedLink.'" target="_blank">'.$mem['first_name'].' '.$mem['last_name'].'</a></td>
                                <td>'.$mem['promo_code'].'</td>';
                    break;
                case 'autorenew':
                    $output .= '
                                <td><a href="'.$encodedLink.'" target="_blank">'.$mem['first_name'].' '.$mem['last_name'].'</a></td>';
                    
                    $val = ($mem['auto_renew'] == 1) ? 'Yes' : 'No';
                    $output .= '
                                <td>'.$val.'</td>';
                    break;
                case 'nonmember':
                    $output .= '
                                <td>'.$mem['first_name'].' '.$mem['last_name'].'</td>';
                    break;
                case 'nhcpin':
                    $output .= '
                                <td>'.$mem['year'].'</td>
                                <td>'.$mem['first_name'].' '.$mem['last_name'].'</td>
                                <td>'.$mem['uid'].'</td>';
                    break;
                default:
                    $output .= '
                                <td><a href="'.$encodedLink.'" target="_blank">'.$mem['first_name'].' '.$mem['last_name'].'</a></td>';
                    break;
            }
                                        
            $output .= '                
                                <td><a href="mailto:'.$mem['user_email'].'">'.$mem['user_email'].'</a></td>';
            if($showLevel) {
                $output .= '
                                <td>'.$mem['level'].'</td>';
            }
            
            $output .= '
                            </tr>';
        }
        
        $output .= '
                    </table>
                </div>';
        
        echo $output;
        
    }
    
    
    
    private function setTableHeaders($searchType, $showLevel=false) {
        switch($searchType) {
            case 'premium':
                $this->headerArr = array('NHC PIN', 'Username', 'Name', 'Address', 'Email');
                $this->exportHeaderArr = array('NHC PIN', 'Username', 'First Name', 'Last Name', 'Email', 'Address1', 'Address2', 'City', 'State', 'Zip', 'Country');
                break;
            case 'promo':
                $this->headerArr = array('NHC PIN', 'Player Name', 'Promo Code', 'Email');
                $this->exportHeaderArr = array('NHC PIN', 'First Name', 'Last Name', 'Promo Code', 'Email');
                break;
            case 'bysignup':
                $this->headerArr = array('NHC PIN', 'Username', 'Signup Date', 'Name', 'Address', 'Email');
                $this->exportHeaderArr = array('NHC PIN', 'Username', 'Email', 'Signup Date', 'First Name', 'Last Name', 'Address1', 'Address2', 'City', 'State', 'Zip', 'Country');
                break;
            case 'autorenew':
                $this->headerArr = array('NHC PIN', 'Player Name', 'Auto Renew', 'Email');
                $this->exportHeaderArr = array('NHC PIN', 'First Name', 'Last Name', 'Auto Renew', 'Email');
                break;
            case 'nonmember':
                $this->headerArr = array('NHC PIN', 'Player Name', 'Email');
                $this->exportHeaderArr = array('NHC PIN', 'First Name', 'Last Name', 'Email');
                break;
            case 'nhcpin':
                $this->headerArr = array('NHC PIN', 'Year', 'Player Name', 'User ID', 'Email');
                $this->exportHeaderArr = array('NHC PIN', 'Year', 'Email', 'First Name', 'Last Name', 'User ID');
                break;
            default:
                $this->headerArr = array('NHC PIN', 'Player Name', 'Email');
                $this->exportHeaderArr = array('NHC PIN', 'First Name', 'Last Name', 'Email');
        }
        
        if($showLevel) {
            $this->headerArr[] = 'Level';
            $this->exportHeaderArr[] = 'Level';
        }
        
        
    }
    
    private function export($members) {
        $uploadDir = wp_upload_dir();
        
        $fh = fopen( $uploadDir['path'].'/NTRA_Report.csv', 'w');

        // output the column headings
        fputcsv($fh, $this->exportHeaderArr);

        // loop over the rows, outputting them
        foreach($members as $m) {
            unset($m['id']);
            fputcsv($fh, $m);
        }
        
        fclose($fh);
        
        echo '<br /><br /><a href="'.$uploadDir['url'].'/NTRA_Report.csv">Download Report</a>';
    }
    
    
    private function new_export($members) {
        $uploadDir = wp_upload_dir();
        
        $fh = fopen( $uploadDir['path'].'/NTRA_Report.csv', 'w');

        // output the column headings
        fputcsv($fh, $this->exportHeaderArr);

        // loop over the rows, outputting them
        foreach($members as $m) {
            unset($m['id']);
            fputcsv($fh, $m);
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="NTRA_Report.csv";');
        header('Pragma: no-cache');
        readfile($uploadDir['path'].'/NTRA_Report.csv');
    }
    
    
    private function getNHCMetaData($key) {
        $query = "SELECT meta_value FROM {$this->db->prefix}nhc_meta WHERE meta_key = '$key'";
        $resultVar = $this->db->get_var($query);
        
        return $resultVar;
    }
    
    
    
    private function runScript() {
        
        // disable this until we need it again
        //return true;
        
        /** This script was used to clean up errant records between the nhc and player rating tables. **/
        $query = "SELECT nhc.nhc_pin FROM `{$this->db->prefix}nhc` nhc
                    JOIN `{$this->db->prefix}nhc_player_rating` pr ON nhc.nhc_pin = pr.nhc_pin";
        $joinResults = $this->db->get_results($query);
        $uniqueArr = $duplicateArr = array();
        foreach($joinResults as $join) {
            if(in_array($join->nhc_pin, $uniqueArr)) {
                $duplicateArr[] = $join->nhc_pin;
            }
            else {
                $uniqueArr[] = $join->nhc_pin;
            }
        }
        
        
        echo'duplicates: <br><pre>'; print_r($duplicateArr); echo '</pre>';
        
        $dupeString = implode(',', $duplicateArr);
        $query2 = "SELECT * from `{$this->db->prefix}nhc` where nhc_pin IN ($dupeString)";
        echo '<br><br>'.$query2;
        $dupResults = $this->db->get_results($query2);
        
        echo'<br><br>duplicate results in nhc table: <br><pre>'; print_r($dupResults); echo '</pre>';
        return true;

    }
    
}
