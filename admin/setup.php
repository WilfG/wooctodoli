<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026		SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    wooctodoli/admin/setup.php
 * \ingroup wooctodoli
 * \brief   WoocToDoli setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

// Security check
if (!$user->admin) {
    accessforbidden();
}

llxHeader('', 'WoocToDoli setup');

$langs->load('admin');
$langs->load('wooctodoli@wooctodoli');

$action = GETPOST('action', 'alpha');

$fields = array(
    'WOOCTODOLI_WOO_URL' => GETPOST('WOOCTODOLI_WOO_URL', 'alpha'),
    'WOOCTODOLI_WOO_CONSUMER_KEY' => GETPOST('WOOCTODOLI_WOO_CONSUMER_KEY', 'alpha'),
    'WOOCTODOLI_WOO_CONSUMER_SECRET' => GETPOST('WOOCTODOLI_WOO_CONSUMER_SECRET', 'alpha'),
    'WOOCTODOLI_IMPORT_ORDER_STATUS' => GETPOST('WOOCTODOLI_IMPORT_ORDER_STATUS', 'alpha')
);

if ($action === 'save') {
    foreach ($fields as $const => $val) {
        dolibarr_set_const($db, $const, $val, 'chaine', 0, '', $conf->entity);
    }
    setEventMessage($langs->trans('RecordSaved'));
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$form = new Form($db);

print load_fiche_titre($langs->trans('ModuleWoocToDoliName'));

print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<table class="noborder" width="100%">';

// WooCommerce URL
print '<tr class="liste_titre"><td colspan="2">' . $langs->trans('WooCommerceSettings') . '</td></tr>';
print '<tr><td width="30%">' . $langs->trans('Wooctodoli_WooUrl') . '</td><td><input class="flat" type="text" name="WOOCTODOLI_WOO_URL" value="' . (isset($conf->global->WOOCTODOLI_WOO_URL) ? $conf->global->WOOCTODOLI_WOO_URL : '') . '" size="80"></td></tr>';
print '<tr><td>' . $langs->trans('Wooctodoli_ConsumerKey') . '</td><td><input class="flat" type="text" name="WOOCTODOLI_WOO_CONSUMER_KEY" value="' . (isset($conf->global->WOOCTODOLI_WOO_CONSUMER_KEY) ? $conf->global->WOOCTODOLI_WOO_CONSUMER_KEY : '') . '" size="80"></td></tr>';
print '<tr><td>' . $langs->trans('Wooctodoli_ConsumerSecret') . '</td><td><input class="flat" type="text" name="WOOCTODOLI_WOO_CONSUMER_SECRET" value="' . (isset($conf->global->WOOCTODOLI_WOO_CONSUMER_SECRET) ? $conf->global->WOOCTODOLI_WOO_CONSUMER_SECRET : '') . '" size="80"></td></tr>';
print '<tr><td>' . $langs->trans('Wooctodoli_ImportOrderStatus') . '</td><td><input class="flat" type="text" name="WOOCTODOLI_IMPORT_ORDER_STATUS" value="' . (isset($conf->global->WOOCTODOLI_IMPORT_ORDER_STATUS) ? $conf->global->WOOCTODOLI_IMPORT_ORDER_STATUS : 'completed') . '" size="20"><br><small>' . $langs->trans('Wooctodoli_ImportOrderStatusDesc') . '</small></td></tr>';

print '</table>';
print '<br><input type="submit" class="button" value="' . $langs->trans('Save') . '">';
print '</form>';

llxFooter();
$db->close();

