<?

/*
 * (c) 2004-2007 Linbox / Free&ALter Soft, http://linbox.com
 * (c) 2007 Mandriva, http://www.mandriva.com
 *
 * $Id$
 *
 * This file is part of Mandriva Management Console (MMC).
 *
 * MMC is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * MMC is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MMC; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

require_once('modules/msc/includes/widgets.inc.php');
require_once('modules/msc/includes/utilities.php');
require_once('modules/msc/includes/qactions.inc.php');
require_once('modules/msc/includes/mirror_api.php');
require_once('modules/msc/includes/commands_xmlrpc.inc.php');
require_once('modules/msc/includes/package_api.php');
require_once('modules/msc/includes/scheduler_xmlrpc.php');
require_once('modules/msc/includes/mscoptions_xmlrpc.php');


function start_command($proxy = array()) {
    // Vars seeding
    $post = $_POST;
    $from = $post['from'];
    $path =  explode('|', $from);
    $module = $path[0];
    $submod = $path[1];
    $page = $path[2];
    $params = array();
    foreach (array('start_script', 'clean_on_success', 'do_reboot', 'do_wol', 'next_connection_delay','max_connection_attempt', 'do_inventory', 'ltitle', 'parameters', 'papi', 'maxbw', 'deployment_intervals') as $param) {
        $params[$param] = $post[$param];
    }
    $p_api = new ServerAPI();
    $p_api->fromURI($post['papi']);

    foreach (array('start_date', 'end_date') as $param) {
        if ($post[$param] == _T("now", "msc")) {
            $params[$param] = "0000-00-00 00:00:00";
        } elseif ($post[$param] == _T("never", "msc")) {
            $params[$param] = "0000-00-00 00:00:00";
        } else
            $params[$param] = $post[$param];
    }

    $pid = $post['pid'];
    $mode = $post['copy_mode'];

    if (isset($post['uuid']) && $post['uuid']) { # command on a single target
        $hostname = $post['hostname'];
        $uuid = $post['uuid'];
        $machine = getMachine(array('uuid'=>$_GET['uuid']), True);
        $target = array($uuid);
        $tab = 'tablogs';
        // record new command
        $id = add_command_api($pid, $target, $params, $p_api, $mode, NULL);
        scheduler_start_these_commands('', array($id));
        // then redirect to the logs page
        header("Location: " . urlStrRedirect("$module/$submod/$page", array('tab'=>$tab, 'uuid'=>$uuid, 'hostname'=>$hostname, 'cmd_id'=>$id)));
    } else { # command on a whole group
        $gid = $post['gid'];
        $tab = 'grouptablogs';
        // record new command
        $id = add_command_api($pid, NULL, $params, $p_api, $mode, $gid, $proxy);
        scheduler_start_these_commands('', array($id));
        // then redirect to the logs page
        header("Location: " . urlStrRedirect("$module/$submod/$page", array('tab'=>$tab, 'gid'=>$gid, 'cmd_id'=>$id, 'proxy' => $proxy)));
    }
}

/* Validation on local proxies selection page */
if (isset($_POST["bconfirmproxy"])) {
    $proxy = array();
    if (isset($_POST["lpmembers"])) {
        $lmachines = unserialize(base64_decode($_POST["lpmachines"]));
        $members = unserialize(base64_decode($_POST["lpmembers"]));
        foreach($members as $member => $name) {
            $computer = preg_split("/##/", $member);
            $proxy[] = $computer[1];
        }
    }
    start_command($proxy);
}

if (isset($_GET['badvanced']) and isset($_POST['local_proxy'])) {
    if (isset($_POST['bback'])) {
        $from = $_POST['from'];
        $path =  explode('|', $from);
        $module = $path[0];
        $submod = $path[1];
        $page = $path[2];
        $gid = $_POST["gid"];
        header("Location: " . urlStrRedirect("$module/$submod/$page", array('tab'=>"grouptablaunch", 'gid'=>$gid)));
    } else
        require('modules/msc/msc/local_proxy.php');

}

/* Advanced Action Post Handling */
if (isset($_GET['badvanced']) and isset($_POST['bconfirm']) and !isset($_POST['local_proxy'])) {
    start_command();
}

/* Advanced action: form display */
if (isset($_GET['badvanced']) and !isset($_POST['bconfirm'])) {
    // Vars seeding
    $from = $_GET['from'];
    $pid = $_GET['pid'];
    $p_api = new ServerAPI();
    $p_api->fromURI($_GET["papi"]);
    $name = getPackageLabel($p_api, $_GET['pid']);

    // form design
    $f = new Form();

    // display top label
    if (isset($_GET['uuid']) && $_GET['uuid']) {
        $hostname = $_GET['hostname'];
        $uuid = $_GET['uuid'];
        $machine = getMachine(array('uuid'=>$uuid), True);

        $hostname = $machine->hostname;
        $label = new RenderedLabel(3, sprintf(_T('Single advanced launch : action "%s" on "%s"', 'msc'), $name, $machine->hostname));

        $f->push(new Table());
        $f->add(new HiddenTpl("uuid"),  array("value" => $uuid,         "hide" => True));
        $f->add(new HiddenTpl("name"),  array("value" => $hostname,     "hide" => True));
    } else {
        $gid = $_GET['gid'];
        $group = new Group($gid, true);
        $label = new RenderedLabel(3, sprintf(_T('Group Advanced launch : action "%s" on "%s"', 'msc'), $name, $group->getName()));

        $f->push(new Table());
        $f->add(new HiddenTpl("gid"),   array("value" => $gid,          "hide" => True));
    }
    $label->display();

    $f->add(new HiddenTpl("pid"),   array("value" => $pid,          "hide" => True));
    $f->add(new HiddenTpl("papi"),  array("value" => $_GET["papi"], "hide" => True));
    $f->add(new HiddenTpl("from"),  array("value" => $from,         "hide" => True));
    $f->add(new TrFormElement(_T('Command name', 'msc'),                                new InputTpl('ltitle')), array("value" => $name));
    $f->add(new TrFormElement(_T('Script parameters', 'msc'),                           new InputTpl('parameters')), array("value" => ''));
    $f->add(new TrFormElement(_T('Start "Wake On Lan" query if connection fails', 'msc'), new CheckboxTpl("do_wol")), array("value" => $_GET['do_wol'] == 'on' ? 'checked' : ''));
    $f->add(new TrFormElement(_T('Start script', 'msc'),                                new CheckboxTpl("start_script")), array("value" => 'checked'));
    $f->add(new TrFormElement(_T('Delete files after a successful execution', 'msc'),   new CheckboxTpl("clean_on_success")), array("value" => 'checked'));
    $f->add(new TrFormElement(_T('Do an inventory after a successful execution', 'msc'),new CheckboxTpl("do_inventory")), array("value" => $_GET['do_inventory'] == 'on' ? 'checked' : ''));
    $f->add(new TrFormElement(_T('Reboot client after a successful deletion', 'msc'),   new CheckboxTpl("do_reboot")), array("value" => $_GET['do_reboot'] == 'on' ? 'checked' : ''));
    $f->add(new TrFormElement(_T('Maximum number of connection attempt', 'msc'),        new InputTpl("max_connection_attempt")), array("value" => $_GET['max_connection_attempt']));
    $f->add(new TrFormElement(_T('Delay between two connections (minutes)', 'msc'),     new InputTpl("next_connection_delay")), array("value" => $_GET['next_connection_delay']));
    $f->add(new TrFormElement(_T('The command may start after', 'msc'),                 new DynamicDateTpl('start_date')), array('ask_for_now' => 1));
    $f->add(new TrFormElement(_T('The command must stop before', 'msc'),                new DynamicDateTpl('end_date')), array('ask_for_never' => 1));
    $f->add(new TrFormElement(_T('Deployment interval', 'msc'),                         new InputTpl('deployment_intervals')), array("value" => $_GET['deployment_intervals']));
    $f->add(new TrFormElement(_T('Max bandwidth (b/s)', 'msc'),                         new NumericInputTpl('maxbw')), array("value" => web_def_maxbw()));
    if (web_force_mode()) {
        $f->add(new HiddenTpl("copy_mode"),         array("value" => web_def_mode(), "hide" => True));
    } else {
        $rb = new RadioTpl("copy_mode");
        $rb->setChoices(array(_T('push', 'msc'), _T('push / pull', 'msc')));
        $rb->setvalues(array('push', 'push_pull'));
        $rb->setSelected($_GET['copy_mode']);
        $f->add(new TrFormElement(_T('Copy Mode', 'msc'), $rb));
    }

    /* Only display local proxy button on a group and if allowed */
    if (isset($_GET['gid']) && strlen($_GET['gid']) && web_allow_local_proxy()) {
        $f->add(new TrFormElement(_T('Deploy using a local proxy', 'msc'),
                                  new CheckboxTpl("local_proxy")), array("value" => ''));
    }

    $f->pop();
    $f->addValidateButton("bconfirm");
    $f->addCancelButton("bback");
    $f->display();

}
### /Advanced actions handling ###

/* single target: form display */
if (!isset($_GET['badvanced']) && $_GET['uuid'] && !isset($_POST['launchAction'])) {
    $machine = getMachine(array('uuid'=>$_GET['uuid']), True);
    if ($machine->uuid != $_GET['uuid']) { // Not matching computer found, show error
        $msc_host = new RenderedMSCHostDontExists($_GET['hostname']);
        $msc_host->headerDisplay();
    } else { // We found a matching computer, display QActions and available packages
        $machine = getMachine(array('uuid'=>$_GET['uuid']), $ping = False);
        $msc_host = new RenderedMSCHost($machine);
        $msc_host->ajaxDisplay();

        $msc_actions = new RenderedMSCActions(msc_script_list_file(), $machine->hostname, array('uuid'=>$_GET['uuid']));
        $msc_actions->display();

        $ajax = new AjaxFilter("modules/msc/msc/ajaxPackageFilter.php?uuid=".$machine->uuid."&hostname=".$machine->hostname);
        $ajax->display();
        $ajax->displayDivToUpdate();
    }
}

/* group display */
if (!isset($_GET['badvanced']) && isset($_GET['gid']) && !isset($_POST['launchAction']) && !isset($_GET['uuid'])) {
    $group = new Group($_GET['gid'], true);
    // Display the actions list
    $msc_actions = new RenderedMSCActions(msc_script_list_file(), $group->getName(), array("gid"=>$_GET['gid']));
    $msc_actions->display();

    $ajax = new AjaxFilter("modules/msc/msc/ajaxPackageFilter.php", "container", array("gid"=>$_GET['gid']));
    $ajax->display();
    print "<br/>";
    $ajax->displayDivToUpdate();
}


?>
<style>
.primary_list { }
.secondary_list {
    background-color: #e1e5e6 !important;
}

</style>
