<?php

/*
 * * Purpose: Allow configuration of EveryZ
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
 * */

require_once 'local/app/everyz/js/everyz.zbxe-customization.js.php';
$moduleName = "zbxe-config";
// ----------------- Update data -----------------------------------------------
$updated = false;
foreach ($_REQUEST as $key => $value) {
    if (strpos($key, 'nf_')) {
        $configKey = substr($key, 4);
        zbxeUpdateConfigValue($configKey, $value);
        $updated = true;
    }
}

if ($updated) {
    show_message(_zeT('New configuration stored on EveryZ!'));
}
// -----------------------------------------------------------------------------

$formData = (new CForm())->cleanItems()->setId('frmConfig');
$buttonSave = (new CList())->addItem((new CSubmit('btnUpdate', _('Update'))));
$dashboard = (new CWidget())->setTitle(_zeT('Customization'));

$dashboardGrid = [[], [], []];

function newWidget($p_id, $p_title, $p_content, $p_expanded = true, $p_icon = []) {
    return (new CUiWidget($p_id, (new CDiv($p_content))->setName('body-' . $p_id)->setAttribute("style", "border: 1; margin: 0px 10px 10px 10px;")))
                    ->setHeader(_($p_title), [$p_icon], false);
}

// Mapas =======================================================================
$table = (new CTable());
$table->addRow(
        (new CFormList())
                ->addRow(_zeT('Title', $moduleName),
                        newComboFilterArray([_("Show"), _zeT("Hide")], 'cnf_map_title_show', zbxeConfigValue('map_title_show'), false)
                )
                ->addRow(_('Color'), new CColor('cnf_map_title_color', zbxeConfigValue('map_title_color'), false))
                ->addRow(_zeT('White mark Color'), new CColor('cnf_map_wmark_color', zbxeConfigValue('map_wmark_color'), false))
)
;
$dashboardGrid[0][0] = newWidget('maps', _zeT("Map options"), $table);
// Empresa =====================================================================
$table = (new CTable());

// Imagens possiveis ===========================================================
$iconList = API::Image()->get([
    'output' => ['imageid', 'name'],
    'filter' => ['imagetype' => IMAGE_TYPE_ICON], 'preservekeys' => true]);
order_result($iconList, 'name');

$idLogoSite = zbxeConfigValue('company_logo_site', 0, "company_logo_site");
$idLogoLogin = zbxeConfigValue('company_logo_login', 0, "company_logo_login");
$idGeoDefaultPOI = zbxeConfigValue('geo_default_poi', 0, "geo_default_poi");

// Constroi combo padrão para seleção de imagem
function comboImageSelect($name, $value) {
    $tmp = new CComboBox('cnf_' . $name, $value);
    $tmp->onChange('javascript:getZbxImage(this.value,"img_' . $name . '");');
    return $tmp;
}

// Combos com logotipos ========================================================
$cmbLogoSite = comboImageSelect('company_logo_site', $idLogoSite);
$cmbLogoLogin = comboImageSelect('company_logo_login', $idLogoLogin);
$cmbDefaultPoi = comboImageSelect('geo_default_poi', $idGeoDefaultPOI);

foreach ($iconList as $icon) {
    $cmbLogoSite->addItem($icon['imageid'], $icon['name']);
    $cmbLogoLogin->addItem($icon['imageid'], $icon['name']);
    $cmbDefaultPoi->addItem($icon['imageid'], $icon['name']);
}

$table->addRow(
        (new CFormList())
                ->addRow(_('Name'), (new CTextBox('cnf_company_name'
                        , zbxeConfigValue('company_name')))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH))
                ->addRow(_zeT('Site Logo'), [$cmbLogoSite
                    , SPACE
                    , (new CNumericBox('cnf_company_logo_width', zbxeConfigValue('company_logo_width'), 3))
                    ->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)])
                ->addRow((new CImg('imgstore.php?iconid=' . $idLogoSite
                        , 'company_logo_img', zbxeConfigValue('company_logo_width'), 25))->setId("img_company_logo_site"))
                ->addRow(_zeT('Login Logo'), $cmbLogoLogin)
                ->addRow((new CImg('imgstore.php?iconid=' . $idLogoLogin, 'company_logo_img', 120, 25))->setId("img_company_logo_login"))
);
$dashboardGrid[1][0] = newWidget('company', _zeT("Company"), $table);

// Options for zab-geo
$table = (new CTable());
$table->addRow(
        (new CFormList())
                ->addRow(_('Token'), (new CTextBox('cnf_geo_token'
                        , zbxeConfigValue('geo_token')))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH))
                ->addRow(_zeT('Default POI'), [$cmbDefaultPoi,
                    (new CImg('imgstore.php?iconid=' . $idGeoDefaultPOI
                    , 'cnf_geo_default_poi', 32, 32))->setId("img_geo_default_poi")])
);
$dashboardGrid[0][1] = newWidget('geo', _zeT("ZabGeo"), $table);

$dashboardRow = [];
for ($row = 0; $row < count($dashboardGrid); $row++) {
    for ($col = 0; $col < count($dashboardGrid[$row]); $col++) {
        $dashboardRow[$row] = (new CDiv($dashboardGrid[$row]))->addClass('cell')->addClass('row');
    }
}

$dashboardTable = (new CDiv($dashboardRow))
        ->addClass('table')
        ->addClass('widget-placeholder');


$formData->addItem($dashboardTable)
        // Itens hidden para identificar o modulo   
        ->addItem(new CInput('hidden', 'action', $action))
        ->addItem((new CDiv($buttonSave))->addClass('cell')->addClass('row'))
;
$dashboard->addItem($formData)->show();