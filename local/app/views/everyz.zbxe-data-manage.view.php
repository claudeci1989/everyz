<?php

/*
 * * Purpose: Export and import data from EveryZ database
 * * Adail Horst - http://spinola.net.br/blog
 * *
 * * This program is free software; you can redistribute it and/or modify
 * * it under the terms of the GNU General Public License as published by
 * * the Free Software Foundation; either version 2 of the License, or
 * * (at your option) any later version.
 * *
 * * This program is distributed in the hope that it will be useful,
 * * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * * GNU General Public License for more details.
 * *
 * * You should have received a copy of the GNU General Public License
 * * along with this program; if not, write to the Free Software
 * * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * TodoS: ===========================================================================
 * */

/* * ***************************************************************************
 * Module Variables
 * ************************************************************************** */
// Definitions -----------------------------------------------------------------
// Module Functions 
// Configuration variables =====================================================
$moduleName = "zbxe-data-manage";
$baseProfile .= $moduleName;
$moduleTitle = 'Data manager';
$report = [];

// Common fields
addFilterActions();

// Specific fields
addFilterParameter("typeExport", T_ZBX_INT, 1, false, false, false);
addFilterParameter("langExport", T_ZBX_STR, '', false, false, false);
addFilterParameter("actionType", T_ZBX_INT, 0, false, false, false);
addFilterParameter("format", T_ZBX_INT, 0, false, false, false);

check_fields($fields);

/* * ***************************************************************************
 * Access Control
 * ************************************************************************** */
if (CWebUser::getType() < USER_TYPE_SUPER_ADMIN) {
    access_deny(ACCESS_DENY_PAGE);
}

/* * ***************************************************************************
 * Module Functions
 * ************************************************************************** */

function newText($text, $class = ZBX_STYLE_GREEN, $margin = "10px") {
    return (new CSpan($text))->addClass($class)->setAttribute('style', 'margin: ' . $margin);
}

function arraySearch($array, $key, $value) {
    foreach ($array as $k => $v) {
        if ($v[$key] == $value) {
            return $k;
        }
    }
    return null;
}

/* * ***************************************************************************
 * Change Data
 * ************************************************************************** */
// Import de dados -----------------------------------------------------
if (isset($_FILES['import_file']) && $filter['actionType'] == 0) {
    $result = false;

    try {
        $file = new CUploadFile($_FILES['import_file']);
        $json = json_decode($file->getContent(), true);

        if (isset($json["translation"])) {
            //$translations = [];
            $resultOK = true;
            DBstart();
            foreach ($json["translation"] as $row) {
                // Populate translations array
                if (!isset($translations[$row['lang']])) {
                    $translations[$row['lang']] = zbxeSQLList('SELECT * FROM `zbxe_translation` '
                            . ' where lang = ' . quotestr($row['lang']) . ' order by tx_original');
                }
                $translate = arraySearch($translations[$row['lang']], 'tx_original', $row['tx_original']);
                if (!isset($translations[$row['lang']][$translate])) {
                    $sql = "insert into zbxe_translation (lang, tx_original, tx_new, module_id) values ("
                            . quotestr($row['lang']) . "," . quotestr($row['tx_original'])
                            . "," . quotestr($row['tx_new']) . ", " . quotestr($row['module_id']) . ")";
                } else {
                    $translate = $translations[$row['lang']][$translate];
                    //var_dump($translate);
                    $sql = ($translate['tx_new'] == $row['tx_new'] ? '' :
                                    'update zbxe_translation set tx_new = ' . quotestr($row['tx_new'])
                                    . ' where lang = ' . quotestr($row['lang']) . ' and tx_original = '
                                    . quotestr($row['tx_original']));
                }
                // Find required
                if (trim($sql) !== '') {
                    $resultOK = $resultOK && (prepareQuery($sql) != false);
                }
            }
            DBend($resultOK);

            //echo '<pre>' . print_r($translations, true) . '</pre>';
        }
    } catch (Exception $e) {
        error($e->getMessage());
    }
    show_messages($resultOK, _('Imported successfully'), _('Import failed'));
}

/* * ***************************************************************************
 * Get Data
 * ************************************************************************** */

switch ($filter['actionType']) {
    case 1;
        switch ($filter['typeExport']) {
            case 1:
                $report['export'] = zbxeSQLList('SELECT * FROM `zbxe_preferences` order by userid, tx_option');
                break;
            case 0:
                $report['export'] = ['translation' => zbxeSQLList('SELECT * FROM `zbxe_translation` '
                            . ($filter['langExport'] != '' ? ' where lang = ' . quotestr($filter['langExport']) : '' ) . ' order by lang, tx_original')
                ];
                break;
        }
        break;
    default;
        ?>
        <script language="JavaScript">
            function changeAction() {
                document.getElementById("import_file").disabled = document.getElementById("actionType_1").checked;
                document.getElementById("format").value = (document.getElementById("actionType_1").checked ? 6 : 0);
            }
        </script>
        <?php

        $report['widgets'] = zbxeFieldValue("SELECT count(*) as total FROM `zbxe_preferences` where tx_option like 'widget%' and tx_option not like '%link%' ", 'total');
        $report['links'] = zbxeFieldValue("SELECT count(*) as total FROM `zbxe_preferences` where tx_option like '%_%link%'  ", 'total');
        $report['strings'] = zbxeFieldValue("SELECT count(*) as total FROM `zbxe_translation` where lang = 'en_GB' ", 'total');
        $report['languages'] = zbxeFieldValue("SELECT COUNT(DISTINCT(lang)) as total FROM `zbxe_translation` ", 'total');

        break;
}

/* * ***************************************************************************
 * Display
 * ************************************************************************** */

switch ($filter["format"]) {
    case PAGE_TYPE_JSON;
        echo json_encode($report["export"], JSON_UNESCAPED_UNICODE);
        break;
    default;
        commonModuleHeader($moduleName, $moduleTitle, true);
        $groupDataButtons = buttonOptions("typeExport", $filter['typeExport'], ['Translation', 'Configuration'], [0, 1]);

        $actionTypeButtons = buttonOptions("actionType", $filter['actionType'], ['Import', 'Export'], [0, 1]);
        $actionTypeButtons->onChange('changeAction();');

        $languages = zbxeSQLList('SELECT DISTINCT(lang) as lang FROM `zbxe_translation` order by lang');
        $languageButtons = buttonOptions("langExport", $filter['langExport'], $languages, $languages);

        $inputImport = (new CFile('import_file'))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
        if ($filter['actionType'] == 1) {
            $inputImport->setAttribute('disabled', 'true');
        }

        $tmpColumn1 = new CFormList();

        $subTable = (new CTableInfo())->setHeader([ _('Widgets'), _('Modules'), _('Strings'), _('Languages'), '']);
        $subTable->addRow([ newText($report['widgets']), newText($report['links']), newText($report['strings']), newText($report['languages'])]);

        $tmpColumn1->addRow([_('Your EveryZ installation currently have:'), $subTable]);

        foreach (getLocales() as $localeId => $locale) {
            if ($locale['display']) {
                $langs[$localeId] = $locale['name'];
            }
        }
        $langComboBox = newComboFilterArray($langs, 'langExport', CWebUser::$data['lang'], false);

        $subTable = (new CTableInfo())->setHeader([ _('Action'), _('Group data'), _('Language'), '']);
        $subTable->addRow([$actionTypeButtons, $groupDataButtons, (new CDiv($langComboBox))->setAttribute('id', "langButtons")]);

        $tmpColumn1->addRow($subTable);
        $tmpColumn1->addRow(_('Import file'), (new CDiv($inputImport))->setAttribute('id', "importDIV"));
        $tmpColumn1->addRow([new CSubmit('form', _('Run'))]);
        $tmpColumn1->setAttribute('style', 'width: 50%;');
        $tmpColumn1->addItem(new CInput('hidden', 'format', PAGE_TYPE_HTML));

        $table->addRow([$tmpColumn1]);
        //$imageForm = (new CForm('post', null, 'multipart/form-data'))
        //->addVar('form', $this->data['form']);
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->addItem([ $table]);
        $dashboard->addItem($form)->show();
        break;
}
