<?php

/*
 * * Purpose: Translation, export and import data
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

// Scripts e CSS adicionais
?>
<?php

// Definitions -----------------------------------------------------------------
// Module Functions 
// Configuration variables =====================================================
$moduleName = "zbxe-data-manage";
$baseProfile .= $moduleName;
$otherGroup = 'Other';

// Common fields
addFilterParameter("format", T_ZBX_INT);
addFilterActions();

// Specific fields
$filter['sourceTranslation'] = [T_ZBX_STR, O_OPT, P_SYS, null, null];
$filter['stringTranslation'] = [T_ZBX_STR, O_OPT, P_SYS, null, null];
//addFilterParameter("sourceTranslation", T_ZBX_STR, [], true, true, false);
//addFilterParameter("stringTranslation", T_ZBX_STR, [], true, true, false);
//CProfile::delete($baseProfile . "." . "sourceTranslation");
//CProfile::delete($baseProfile . "." . "stringTranslation");

check_fields($fields);

/*
 * Get Data
 */

$dataTab = new CTabView();
$lang = CWebUser::$data['lang'];

/* Update user translations if needed */
//var_dump(getRequest("stringTranslation",[]));
parse_str(file_get_contents('php://input'), $httpParams);
$updated = false;
$dml = false;
$sql = $dmlReport = "";
if (isset($httpParams['stringTranslation'])) {
    foreach ($httpParams['stringTranslation'] as $key => $value) {
        $sourceString = $httpParams['sourceTranslation'][$key];
        if ($sourceString !== $value) {
            $current = zbxeFieldValue("select tx_new from zbxe_translation where tx_original = "
                    . quotestr($sourceString) . " and lang=" . quotestr($lang), "tx_new");
            if ($current !== $value) {
                $sql = zbxeUpdate('zbxe_translation', ['tx_new'], [$value], ['tx_original', 'lang'], [$sourceString, $lang]);
                $dml = true;
                $dmlReport .= 'Update translation for string "' . $sourceString;
                prepareQuery($sql);
            }
        }
    }
}
if ($dml) {
    show_message('Strings de tradução atualiadas!');
}

$query = 'SELECT tx_original, module_id FROM `zbxe_translation` zet where lang="en_GB" and tx_original <> "Everyz" order by module_id';
$result = prepareQuery($query);
$strings = [];

while ($row = DBfetch($result)) {
    $translate = _zeT($row['tx_original'], $row['module_id']);
    $next = (isset($strings[$row['module_id']]) ? count($strings[$row['module_id']]) : 0);
    $strings[$row['module_id']][$next] = [$row['tx_original'], ($translate == "" ? $row['tx_original'] : $translate)];
}

// Agrupando strings originais
$report = [];
foreach ($strings as $key => $value) {
    $key2 = ($key == "" || count($strings[$key]) < 10 ? $otherGroup : $key);
    foreach ($value as $tmp2) {
        $next = (isset($report[$key2]) ? count($report[$key2]) : 0);
        $report[$key2][$next] = [$tmp2[0], $tmp2[1], $key];
    }
}



/*
 * Display
 */
$dashboard = (new CWidget())
        ->setTitle(EZ_TITLE . _zeT('Translation and data management'))
        ->setControls((new CList())
        ->addItem(get_icon('fullscreen', ['fullscreen' => getRequest('fullscreen')]))
);
$toggle_all = (new CColHeader(
        (new CDiv())
                ->addClass(ZBX_STYLE_TREEVIEW)
                ->addClass('app-list-toggle-all')
                ->addItem(new CSpan())
        ))->addStyle('width: 18px');
$form = (new CForm('POST', 'everyz.php'))->setName($moduleName);

function addTab($key, $value, $dataTab) {
    global $lang;
    $tmp = explode("|", zbxeFieldValue("SELECT tx_value FROM zbxe_preferences where tx_option like 'widget_%_link_%' and tx_value like '" .
                    $key . "|%' ", "tx_value"));
    $desc = (count($tmp) == 2 ? $tmp[1] : $key);
    $tabContent = new CFormList();
    $tabContent->addRow(bold(_zeT("Source")), bold(_zeT("Translation")));
    foreach ($value as $tmp2) {
        $tabContent->addRow($tmp2[0], ( $lang == "en_GB" ? $tmp2[1] : (new CTextBox('stringTranslation[]', $tmp2[1]))->setWidth(ZBX_TEXTAREA_FILTER_BIG_WIDTH)));
        $tabContent->addItem(new CInput('hidden', 'sourceTranslation[]', $tmp2[0]));
    }
    $dataTab->addTab($key, $desc, $tabContent);
}

foreach ($report as $key => $value) {
    if ($key !== $otherGroup) {
        addTab($key, $value, $dataTab);
    }
}
addTab(_($otherGroup), $report[$otherGroup], $dataTab);
// Deve possuir botão para exportar e para importar
$dataTab->setFooter(makeFormFooter(new CSubmit('update', _('Update'))));
$dataTab->addItem(new CInput('hidden', 'action', $filter['action']));


$form->addItem([$dataTab]);

$dashboard->addItem($form)->show();
