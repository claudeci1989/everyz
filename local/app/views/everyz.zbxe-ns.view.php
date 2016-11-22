<?php

/*
 * * Purpose: Report of Not Supported Items
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

require_once 'include/views/js/monitoring.latest.js.php';

// Configuration variables =====================================================
$moduleName = "zbxe-ns";
$baseProfile .= $moduleName;

// Common fields
addFilterParameter("format", T_ZBX_INT);
addFilterActions();

// Specific fields
addFilterParameter("hostids", PROFILE_TYPE_STR, [], true, true);
addFilterParameter("item", T_ZBX_STR);
addFilterParameter("groupids", PROFILE_TYPE_STR, [], true, true);
//addFilterParameter("application", T_ZBX_STR);

check_fields($fields);
/*
 * Security
 */
if (getRequest('groupids') && !API::HostGroup()->isReadable(getRequest('groupids'))) {
    access_deny();
}
if (getRequest('hostids') && !API::Host()->isReadable(getRequest('hostids'))) {
    access_deny();
}
/*
 * Display
 */
$dashboard = (new CWidget())
        ->setTitle('EveryZ - ' . _zeT('Not Supported Items'))
        ->setControls(fullScreenIcon()
);
$toggle_all = (new CColHeader(
        (new CDiv())
                ->addClass(ZBX_STYLE_TREEVIEW)
                ->addClass('app-list-toggle-all')
                ->addItem(new CSpan())
        ))->addStyle('width: 18px');
$form = (new CForm('POST', 'everyz.php'))->setName('ns');
//$form = (new CForm('GET', 'everyz.php?action=zbxe-ns'))->setName('ns');
$table = (new CTableInfo())->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);

// Filtros =====================================================================
if (hasRequest('filter_rst')) { // Clean the filter parameters
    resetProfile('hostids', true);
    resetProfile('groupids', true);
    resetProfile('item');
    //resetProfile('application');
    $filter['filter_rst'] = NULL;
    $filter['filter_set'] = NULL;
}
// Get the multiselect hosts
$multiSelectHostData = selectedHosts($filter['hostids']);
// Get the multiselect hosts
$multiSelectHostGroupData = selectedHostGroups($filter['groupids']);

$widget = (new CFilter('web.latest.filter.state'));

// Source data filter
$tmpColumn = new CFormList();
$tmpColumn->addRow(_('Host Groups'), multiSelectHostGroups($multiSelectHostGroupData))
        ->addRow(_('Key'), [ (new CTextBox('item', $filter['item']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
            (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
            (new CButton('item_name', _('Select')))
            ->addClass(ZBX_STYLE_BTN_GREY)
            ->onClick('return PopUp("popup.php?srctbl=items&srcfld1=key_&real_hosts=1&dstfld1=item' .
                    '&with_items=1&dstfrm=zbx_filter");')
        ])
;
$tmpColumn->addItem(new CInput('hidden', 'action', $filter["action"]));
$widget->addColumn($tmpColumn);

$tmpColumn = (new CFormList())
        ->addRow(_('Hosts'), multiSelectHosts($multiSelectHostData))
        /* ->addRow(_('Application'), [ (new CTextBox('application', $filter['application']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
          (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
          (new CButton('application_name', _('Select')))
          ->addClass(ZBX_STYLE_BTN_GREY)
          ->onClick('return PopUp("popup.php?srctbl=applications&srcfld1=name&real_hosts=1&dstfld1=application&with_applications=1&dstfrm=zbx_filter");')
          ]) */
        ->addRow(_zeT('Output format'), (new CRadioButtonList('format', (int) $filter['format']))->addValue('HTML', 0)->addValue('CSV', 1)->setModern(true))
;
$widget->addColumn($tmpColumn);


$dashboard->addItem($widget);
$report = Array();
$hostCont = Array();
// Get data for report ---------------------------------------------------------
if (hasRequest('filter_set')) {

    $hostFilter = zbxeDBConditionInt('hos.hostid', $filter["hostids"]);
    $hostGroupFilter = zbxeDBConditionInt('hg.groupid', $filter["groupids"]);
    if ($hostGroupFilter !== "") {
        $hostGroupFilter = "\n inner join hosts_groups hg \n on (hg.hostid = ite.hostid) AND " . $hostGroupFilter;
    }
    /* Todo: Add a application filter
      if (getRequest('application') !== "") {
      $applicationFilter = "\n inner join items_applications ita \n on (ita.itemid = ite.itemid) "
      . "\n inner join applications app \n on (app.name like \"" . quotestr(getRequest('application'). "%") . "\") "
      . "\n AND (app.applicationid = ita.applicationid) "
      ;
      } else {
      $applicationFilter = "";
      }
     */
    $query = 'select hos.host, hos.name as visible_name, ite.name, ite.itemid, hos.hostid, ite.error, ite.key_ ' .
            '  from items ite ' .
            '  inner join hosts hos ' .
            '     on (hos.hostid = ite.hostid) '
            . ($hostFilter == "" ? "" : " AND ") . $hostFilter
            . $hostGroupFilter
            . ' where ite.state = 1 AND ite.status = 0 '
            . ($filter["item"] == "" ? "" : ' AND ite.name like ' . quotestr($filter["item"] . "%"))
            . ' order by hos.host, ite.name'
    ;
    //var_dump ("<br>".$query."<br>");
    // Build a list of items with required key ---------------------------------
    $result = DBselect($query);
    $cont = 0;
    while ($rowItem = DBfetch($result)) {
        $report[$cont]['host_name'] = ($rowItem["visible_name"] !== "" ? $rowItem["visible_name"] : $rowItem["host"]);
        $report[$cont]['itemid'] = $rowItem["itemid"];
        $report[$cont]['hostid'] = $rowItem["hostid"];
        $report[$cont]['error'] = $rowItem["error"];
        $report[$cont]['key_'] = $rowItem["key_"];
        $cont++;
    }
    // Todo: Check if user have access to this hosts
} else {
    $table->setNoDataMessage(_('Specify some filter condition to see the values.'));
}

// Build the report ------------------------------------------------------------
switch ($filter["format"]) {
    case 1;
        $table->setHeader(array(_zeT("Data")));
        break;
    case 0;
        $table->setHeader(array($toggle_all, (new CColHeader(_('Host')))
            //->addStyle('width: 15%')
            , _('Item'), _('Key'), _('Error'), _('Actions')));
        break;
}
$lastHostID = -1;

foreach ($report as $row) {
    $item = getItem($row['itemid']);
    $state_css = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? ZBX_STYLE_GREY : null;
    if ($filter["format"] == 0 && $row['hostid'] !== $lastHostID) {
        $resumo = zbxeFieldValue("select count(*) as total from items ite "
                . "where ite.state = 1 and ite.status = 0 and ite.hostid = " . $row['hostid']
                . ($filter["item"] == "" ? "" : ' AND ite.name like ' . quotestr($filter["item"] . "%"))
                , 'total'
        );
        $table->addRow([
                    (new CDiv())
                    ->addClass(ZBX_STYLE_TREEVIEW)
                    ->addClass('app-list-toggle')
                    ->setAttribute('data-app-id', $row['hostid'])
                    ->setAttribute('data-open-state', 1)
                    ->addItem(new CSpan())
            , $row["host_name"]
            , (new CCol("(" . _n('%1$s Item', '%1$s Items', $resumo) . ")"))->setColSpan(4)
        ]);
    }
    $lastHostID = $row["hostid"];
    switch ($filter["format"]) {
        case 1;
            $table->addRow(quotestr($row["host_name"])
                    . ";" . quotestr($item['name_expanded'])
                    . ";" . quotestr($item['key_'])
                    . ";" . quotestr($row['error'])
            )
            ;
            break;
        case 0;
            $tableRow = new CRow([
                '', ''
                , (new CCol($item["name_expanded"], 1))->addClass($state_css)
                , (new CCol($item["key_"], 1))->addClass($state_css)
                , (new CCol($row["error"], 1))->addClass($state_css)
                , [new CLink(_('Disable'), 'items.php?group_itemid=' . $row['itemid'] . '&hostid=' . $row['hostid'] . '&action=item.massdisable')]
            ]);
            $tableRow->setAttribute('parent_app_id', $row['hostid']);
            $table->addRow($tableRow);
            break;
    }
}
$form->addItem([ $table]);

$dashboard->addItem($form)->show();