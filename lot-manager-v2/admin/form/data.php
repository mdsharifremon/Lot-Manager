<?php
/**
 * Form data
 * Fixed choices, required-field rules, field layout, suggestion lists.
 */
if (!defined('ABSPATH')) exit;

/** Fixed choices. Values are exactly what gets stored in the existing columns. */
function mccm_choices() {
    return array(
        'GENDER'     => array('M' => 'Male', 'F' => 'Female', 'O' => 'Other', 'U' => 'Prefer not to say'),
        'MARITAL'    => array('M' => 'Married', 'W' => 'Widowed', 'S' => 'Single', 'D' => 'Divorced', 'Unk' => 'Unknown'),
        'LEADIN'     => array('Yes' => 'Yes', 'No' => 'No'),
        'CEMETERY'   => array('Wyuka' => 'Wyuka', 'Fairview' => 'Fairview'),
        'LORELATION' => array('Self' => 'Self', 'Spouse' => 'Spouse', 'Son' => 'Son', 'Daughter' => 'Daughter', 'Mother' => 'Mother', 'Father' => 'Father', 'Brother' => 'Brother', 'Sister' => 'Sister'),
        'MILITARY'   => array('No' => 'No', 'Yes' => 'Yes', 'Army' => 'Army', 'Navy' => 'Navy', 'Air Force' => 'Air Force', 'Marines' => 'Marines'),
        'CONTACT_STATE' => mccm_us_states(),
    );
}

/** Fields that offer an "Other..." choice that opens a text box. */
function mccm_other_fields() {
    return array('CEMETERY', 'LORELATION', 'MILITARY');
}

function mccm_us_states() {
    return array('AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','DC'=>'District of Columbia','FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland','MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont','VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming');
}

/** Fields required on NEW records (each is filled in 97%+ of the records added since 2020). On EDIT a field is only required if that record already had a value. */
function mccm_required_fields() {
    return array('LAST' => 'Last name', 'FIRST' => 'First name', 'INTERMENT' => 'Interment number', 'CEMETERY' => 'Cemetery', 'SECTION' => 'Section', 'SPACE' => 'Space', 'BIRTH' => 'Birth date', 'GENDER' => 'Gender');
}

/** Needed on EVERY save (new and edit): where the grave locator can find them (99.8%+ of all records have these). Lot is not required: 18% of records have none, mostly whole sections (28, 1 A, 3 A, 24 SC) where lots do not apply. */
function mccm_always_required() {
    return array('CEMETERY', 'SECTION', 'SPACE'); // plus: at least one name (first or last), see mccm_validate_entry_clean
}

/** Form layout: sections => fields. type: text|select|selother|date|number|suggest|year|zip */
function mccm_form_layout() {
    return array(
        array('id' => 'deceased', 'title' => 'Deceased', 'nav' => 'Deceased', 'icon' => 'user', 'desc' => 'Name, dates and background of the person buried.', 'fields' => array(
            array('INTERMENT', 'Interment number', 'text', 'counter' => true, 'ph' => 'e.g. 1291'),
            array('LAST', 'Last name', 'text', 'ph' => 'Last name'),
            array('FIRST', 'First name', 'text', 'ph' => 'First name'),
            array('MIDDLE', 'Middle name', 'text', 'ph' => 'Middle name'),
            array('MAIDEN', 'Maiden name', 'text', 'ph' => 'Maiden name'),
            array('GENDER', 'Gender', 'select'),
            array('MARITAL', 'Marital status', 'select'),
            array('BIRTH', 'Birth date', 'date'),
            array('DEATH', 'Death date', 'date'),
            array('BURIAL', 'Burial date', 'date'),
            array('DISINTERMENT', 'Disinterment date', 'date', 'hint' => 'Only if the remains were moved.'),
            array('AGE', 'Age', 'number', 'hint' => 'Filled in from birth and death dates when possible.'),
            array('POB', 'Place of birth', 'text', 'ph' => 'City, state'),
            array('POD', 'Place of death', 'text', 'ph' => 'City, state'),
            array('COD', 'Cause of death', 'text', 'ph' => 'Cause of death'),
            array('MILITARY', 'Military service', 'selother'),
        )),
        array('id' => 'location', 'title' => 'Location', 'nav' => 'Location', 'icon' => 'pin', 'desc' => 'Where in the cemetery the grave is, and the deed.', 'fields' => array(
            array('CEMETERY', 'Cemetery', 'selother'),
            array('SECTION', 'Section', 'text', 'counter' => true, 'ph' => 'e.g. 28 or 1 A'),
            array('LOT', 'Lot', 'text', 'counter' => true, 'ph' => 'e.g. 12'),
            array('SPACE', 'Space', 'text', 'counter' => true, 'ph' => 'e.g. 3'),
            array('DEED', 'Deed number', 'text', 'ph' => 'Deed number'),
        )),
        array('id' => 'burial', 'title' => 'Burial details', 'nav' => 'Burial', 'icon' => 'heart', 'desc' => 'Vault, marker, funeral home and charges.', 'fields' => array(
            array('VAULT', 'Vault', 'suggest', 'ph' => 'Type or select vault'),
            array('MARKER', 'Marker', 'suggest', 'ph' => 'Type or select marker'),
            array('LEADIN', 'Lead-in', 'select'),
            array('FUNERALHOME', 'Funeral home', 'suggest', 'ph' => 'Type or select funeral home'),
            array('DIRECTOR', 'Funeral director', 'suggest', 'ph' => 'Type or select director'),
            array('CHARGES', 'Charges', 'text', 'ph' => 'Amount or short note'),
            array('FAMILY', 'Family', 'text', 'ph' => 'Amount or short note'),
            array('MORTUARY', 'Mortuary', 'text', 'ph' => 'Amount or short note'),
        )),
        array('id' => 'owner', 'title' => 'Lot owner and family', 'nav' => 'Owner & family', 'icon' => 'users', 'desc' => 'The lot owner is the person who bought the burial rights to this lot. Family details are optional.', 'fields' => array(
            array('LOLAST', 'Lot owner last name', 'text', 'ph' => 'Owner last name'),
            array('LOFIRST', 'Lot owner first name', 'text', 'ph' => 'Owner first name'),
            array('LORELATION', 'Owner relationship to deceased', 'selother'),
            array('FATHERLAST', 'Father last name', 'text', 'ph' => 'Father last name'),
            array('FATHERFIRST', 'Father first name', 'text', 'ph' => 'Father first name'),
            array('MOTHERLAST', 'Mother last name', 'text', 'ph' => 'Mother last name'),
            array('MOTHERFIRST', 'Mother first name', 'text', 'ph' => 'Mother first name'),
            array('SPOUSE', 'Spouse name', 'text', 'ph' => 'Spouse name'),
        )),
        array('id' => 'contact', 'title' => 'Contact', 'nav' => 'Contact', 'icon' => 'card', 'desc' => 'Who the office should write to about this record. Optional.', 'fields' => array(
            array('CONTACT_NAME', 'Contact name', 'text', 'ph' => 'Full name'),
            array('CONTACT_STREET1', 'Street 1', 'text', 'ph' => 'Street address'),
            array('CONTACT_STREET2', 'Street 2', 'text', 'ph' => 'Apartment, suite, PO box'),
            array('CONTACT_CITY', 'City', 'text', 'ph' => 'City'),
            array('CONTACT_STATE', 'State', 'select'),
            array('CONTACT_ZIP', 'ZIP', 'zip'),
            array('CONTACT_YEAR', 'Year', 'year'),
        )),
    );
}

function mccm_icon($name) {
    $p = array(
        'user'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'pin'   => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'card'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="12" cy="11" r="3"/><path d="M7 18c1-2 3-3 5-3s4 1 5 3"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'alert'    => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
    );
    return '<svg class="mccm-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

/** Column length limits (varchar(N)) read from the real table. */
function mccm_col_limits() {
    global $wpdb;
    $lim = array();
    foreach ((array) $wpdb->get_results("SHOW COLUMNS FROM `" . $wpdb->prefix . "mccm`") as $c) {
        if (preg_match('/^varchar\((\d+)\)/i', $c->Type, $m)) $lim[$c->Field] = (int) $m[1];
    }
    return $lim;
}

/** Suggestions for Section/Vault/Marker/Funeral home/Director. One cached query set, no requests while typing. */
function mccm_suggestions() {
    $cached = get_transient('mccm_suggest_v2');
    if (is_array($cached)) return $cached;
    global $wpdb;
    $t = $wpdb->prefix . 'mccm';
    $out = array();
    // [column => [shown when the box is empty = used at least N times; typing searches everything used 5+ times]]
    foreach (array('VAULT' => 100, 'MARKER' => 25, 'FUNERALHOME' => 25, 'DIRECTOR' => 100) as $col => $top) {
        // skip values that contain a backslash (old double-escaped data)
        $rows = $wpdb->get_results("SELECT `$col` v, COUNT(*) n FROM `$t` WHERE `$col` <> '' AND INSTR(`$col`, CHAR(92)) = 0 GROUP BY `$col` HAVING COUNT(*) >= 5 ORDER BY COUNT(*) DESC LIMIT 150");
        $vals = array(); $shown = 0;
        foreach ((array) $rows as $r) {
            $v = trim((string) $r->v);
            if ($v === '') continue;
            $vals[] = $v;
            if ((int) $r->n >= $top) $shown++;
        }
        $out[$col] = array('values' => $vals, 'top' => $shown);
    }
    set_transient('mccm_suggest_v2', $out, 12 * HOUR_IN_SECONDS);
    return $out;
}
