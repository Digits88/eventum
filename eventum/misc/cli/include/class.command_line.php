<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Eventum - Issue Tracking System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003, 2004 MySQL AB                                    |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 59 Temple Place - Suite 330                                          |
// | Boston, MA 02111-1307, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: Jo�o Prado Maia <jpm@mysql.com>                             |
// +----------------------------------------------------------------------+
//
// @(#) $Id: s.class.command_line.php 1.6 03/12/31 17:32:20-00:00 jpradomaia $
//

include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_PEAR_PATH . "XML_RPC/RPC.php");

class Command_Line
{
    /**
     * Method used to parse the eventum command line configuration file
     * and return the appropriate configuration settings.
     *
     * @access  public
     * @return  array The configuration settings
     */
    function getEnvironmentSettings()
    {
        $rcfile = getenv('HOME') . "/.eventumrc";

        $email = '';
        $password = '';
        $host = '';
        $port = '';
        if (file_exists($rcfile)) {
            $fp = fopen($rcfile, 'r');
            if (!$fp) {
                die("Couldn't open eventum rcfile '$rcfile'\n");
            }
            $lines = explode("\n", fread($fp, filesize($rcfile)));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                $var = trim(substr($line, 0, strpos($line, '=')));
                $value = trim(substr($line, strpos($line, '=')+1));
                if ($var == 'EVENTUM_USER') {
                    $email = $value;
                } elseif ($var == 'EVENTUM_PASSWORD') {
                    $password = $value;
                } elseif ($var == 'EVENTUM_HOST') {
                    $host = $value;
                } elseif ($var == 'EVENTUM_PORT') {
                    $port = $value;
                }
            }
        } else {
            die("Configuration file '$rcfile' could not be found\n");
        }
        return array($email, $password, $host, $port);
    }


    /**
     * Method used to lock and assign an issue to the current user.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   integer $issue_id The issue ID
     * @param   string $email The email address of the current user
     * @param   boolean $force_lock Whether to force the re-assignment or not
     */
    function lockIssue($rpc_conn, $issue_id, $email, $force_lock)
    {
        $projects = Command_Line::getUserAssignedProjects($rpc_conn, $email);

        $msg = new XML_RPC_Message("getIssueDetails", array(new XML_RPC_Value($issue_id, 'int')));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $details = XML_RPC_decode($result->value());
        // check if the issue the user is trying to change is inside a project viewable to him
        $found = 0;
        for ($i = 0; $i < count($projects); $i++) {
            if ($details['iss_prj_id'] == $projects[$i]['id']) {
                $found = 1;
                break;
            }
        }
        if (!$found) {
            Command_Line::quit("The assigned project for issue #$issue_id doesn't match any in the list of projects assigned to you");
        }

        $params = array(
            new XML_RPC_Value($issue_id, 'int'),
            new XML_RPC_Value($email),
            new XML_RPC_Value($force_lock)
        );
        $msg = new XML_RPC_Message("lockIssue", $params);
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        echo "OK - Issue #$issue_id successfully locked and assigned to you.\n";
    }



    /**
     * Method used to assign an issue to the current user.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   integer $issue_id The issue ID
     * @param   string $email The email address of the current user
     * @param   string $developer The email address of the assignee
     */
    function assignIssue($rpc_conn, $issue_id, $email, $developer)
    {
        $projects = Command_Line::getUserAssignedProjects($rpc_conn, $email);

        // check if the given email address is indeed an email
        if (!strstr($developer, '@')) {
            Command_Line::quit("The third argument for this command needs to be a valid email address");
        }

        $msg = new XML_RPC_Message("getIssueDetails", array(new XML_RPC_Value($issue_id, 'int')));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $details = XML_RPC_decode($result->value());
        // check if the issue the user is trying to change is inside a project viewable to him
        $found = 0;
        for ($i = 0; $i < count($projects); $i++) {
            if ($details['iss_prj_id'] == $projects[$i]['id']) {
                $found = 1;
                break;
            }
        }
        if (!$found) {
            Command_Line::quit("The assigned project for issue #$issue_id doesn't match any in the list of projects assigned to you");
        }

        $params = array(
            new XML_RPC_Value($issue_id, 'int'),
            new XML_RPC_Value($details['iss_prj_id'], 'int'),
            new XML_RPC_Value($email),
            new XML_RPC_Value($developer)
        );
        $msg = new XML_RPC_Message("assignIssue", $params);
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        echo "OK - Issue #$issue_id successfully assigned to '$developer'\n";
    }


    /**
     * Method used to change the status of an issue.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   integer $issue_id The issue ID
     * @param   string $email The email address of the current user
     * @param   string $new_status The new status title
     */
    function setIssueStatus($rpc_conn, $issue_id, $email, $new_status)
    {
        $projects = Command_Line::getUserAssignedProjects($rpc_conn, $email);

        $msg = new XML_RPC_Message("getIssueDetails", array(new XML_RPC_Value($issue_id, 'int')));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $details = XML_RPC_decode($result->value());
        // check if the issue the user is trying to change is inside a project viewable to him
        $found = 0;
        for ($i = 0; $i < count($projects); $i++) {
            if ($details['iss_prj_id'] == $projects[$i]['id']) {
                $found = 1;
                break;
            }
        }
        if (!$found) {
            Command_Line::quit("The assigned project for issue #$issue_id doesn't match any in the list of projects assigned to you");
        }

        // check if the issue already is set to the new status
        if (strtolower($details['sta_title']) == strtolower($new_status)) {
            Command_Line::quit("Issue #$issue_id is already set to status '" . $details['sta_title'] . "'");
        }

        // check if the given status is a valid option
        $msg = new XML_RPC_Message("getStatusList", array(new XML_RPC_Value($details['iss_prj_id'], "int")));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $statuses = XML_RPC_decode($result->value());
        $statuses = array_map('strtolower', $statuses);
        if (!in_array(strtolower($new_status), $statuses)) {
            Command_Line::quit("Status '$new_status' could not be matched against the list of available statuses");
        }

        $params = array(
            new XML_RPC_Value($issue_id, 'int'),
            new XML_RPC_Value($email),
            new XML_RPC_Value($new_status)
        );
        $msg = new XML_RPC_Message("setIssueStatus", $params);
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        echo "OK - Status successfully changed to '$new_status' on issue #$issue_id\n";
    }


    /**
     * Method used to add a time tracking entry to an existing issue.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   integer $issue_id The issue ID
     * @param   string $email The email address of the current user
     * @param   string $time_spent The time spent in minutes
     */
    function addTimeEntry($rpc_conn, $issue_id, $email, $time_spent)
    {
        $projects = Command_Line::getUserAssignedProjects($rpc_conn, $email);

        $msg = new XML_RPC_Message("getIssueDetails", array(new XML_RPC_Value($issue_id, 'int')));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $details = XML_RPC_decode($result->value());
        // check if the issue the user is trying to change is inside a project viewable to him
        $found = 0;
        for ($i = 0; $i < count($projects); $i++) {
            if ($details['iss_prj_id'] == $projects[$i]['id']) {
                $found = 1;
                break;
            }
        }
        if (!$found) {
            Command_Line::quit("The assigned project for issue #$issue_id doesn't match any in the list of projects assigned to you");
        }

        // list the time tracking categories
        $msg = new XML_RPC_Message("getTimeTrackingCategories");
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $cats = XML_RPC_decode($result->value());

        $prompt = "Which time tracking category would you like to associate with this time entry?\n";
        foreach ($cats as $id => $title) {
            $prompt .= sprintf(" [%s] => %s\n", $id, $title);
        }
        $prompt .= "Please enter the number of the time tracking category";
        $cat_id = Misc::prompt($prompt, false);
        if (!in_array($cat_id, array_keys($cats))) {
            Command_Line::quit("The selected time tracking category number didn't match any existing category");
        }

        $prompt = "Please enter a quick summary of what you worked on";
        $summary = Misc::prompt($prompt, false);

        $params = array(
            new XML_RPC_Value($issue_id, 'int'),
            new XML_RPC_Value($email),
            new XML_RPC_Value($cat_id, 'int'),
            new XML_RPC_Value($summary),
            new XML_RPC_Value($time_spent, 'int')
        );
        $msg = new XML_RPC_Message("recordTimeWorked", $params);
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        echo "OK - Added time tracking entry to issue #$issue_id\n";
    }


    /**
     * Method used to print the current details for a given issue.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   integer $issue_id The issue ID
     * @param   string $email The email address of the current user
     */
    function printIssueDetails($rpc_conn, $issue_id, $email)
    {
        $projects = Command_Line::getUserAssignedProjects($rpc_conn, $email);

        $msg = new XML_RPC_Message("getIssueDetails", array(new XML_RPC_Value($issue_id, 'int')));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $details = XML_RPC_decode($result->value());
        // check if the issue the user is trying to change is inside a project viewable to him
        $found = 0;
        for ($i = 0; $i < count($projects); $i++) {
            if ($details['iss_prj_id'] == $projects[$i]['id']) {
                $found = 1;
                break;
            }
        }
        if (!$found) {
            Command_Line::quit("The assigned project for issue #$issue_id doesn't match any in the list of projects assigned to you");
        }

        $msg = "      Issue #: $issue_id
      Summary: " . $details['iss_summary'] . "
       Status: " . $details['sta_title'] . "
   Assignment: " . $details['assignments'] . "
Last Response: " . $details['iss_last_response_date'] . "
 Last Updated: " . $details['iss_updated_date'] . "\n";
        echo $msg;
    }


    /**
     * Method used to lock and assign an issue to the current user.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   string $email The email address of the current user
     * @param   string $show_all_issues Whether to show all open issues or just the ones assigned to the current user
     */
    function printOpenIssues($rpc_conn, $email, $show_all_issues)
    {
        $msg = new XML_RPC_Message("getOpenIssues", array(new XML_RPC_Value($email), new XML_RPC_Value($show_all_issues, 'boolean')));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $issues = XML_RPC_decode($result->value());
        echo "The following issues are still open:\n";
        foreach ($issues as $issue) {
            echo "- #" . $issue['issue_id'] . " - " . $issue['summary'] . " (" . $issue['status'] . ")";
            if (!empty($issue['assignment'])) {
                echo " - (" . $issue['assignment'] . ")";
            } else {
                echo " - (unassigned)";
            }
            echo "\n";
        }
    }


    /**
     * Method used to get the list of projects assigned to a given email address.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   string $email The email address of the current user
     * @return  array The list of projects
     */
    function getUserAssignedProjects($rpc_conn, $email)
    {
        $msg = new XML_RPC_Message("getUserAssignedProjects", array(new XML_RPC_Value($email)));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        return XML_RPC_decode($result->value());
    }


    /**
     * Method used to prompt the current user to select a project.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   string $email The email address of the current user
     * @return  integer The project ID
     */
    function promptProjectSelection($rpc_conn, $email)
    {
        // list the projects that this user is assigned to
        $projects = Command_Line::getUserAssignedProjects(&$rpc_conn, $email);

        if (count($projects) > 1) {
            // need to ask which project this person is asking about
            $prompt = "From which project do you want to list available developers?\n";
            for ($i = 0; $i < count($projects); $i++) {
                $prompt .= sprintf(" [%s] => %s\n", $projects[$i]['id'], $projects[$i]['title']);
            }
            $prompt .= "Please enter the number of the project";
            $project_id = Misc::prompt($prompt, false);
            $found = 0;
            for ($i = 0; $i < count($projects); $i++) {
                if ($project_id == $projects[$i]['id']) {
                    $found = 1;
                    break;
                }
            }
            if (!$found) {
                Command_Line::quit("Entered project number doesn't match any in the list of projects assigned to you");
            }
        } else {
            $project_id = $projects[0]['id'];
        }
        return $project_id;
    }


    /**
     * Method used to print the available statuses associated with the
     * currently selected project.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   string $email The email address of the current user
     */
    function printStatusList($rpc_conn, $email)
    {
        $project_id = Command_Line::promptProjectSelection(&$rpc_conn, $email);
        $msg = new XML_RPC_Message("getStatusList", array(new XML_RPC_Value($project_id, "int")));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $items = XML_RPC_decode($result->value());
        echo "Available Statuses:\n";
        foreach ($items as $item) {
            echo "- $item\n";
        }
    }


    /**
     * Method used to print the list of developers.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   string $email The email address of the current user
     */
    function printDeveloperList($rpc_conn, $email)
    {
        $project_id = Command_Line::promptProjectSelection(&$rpc_conn, $email);
        $msg = new XML_RPC_Message("getDeveloperList", array(new XML_RPC_Value($project_id, "int")));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $developers = XML_RPC_decode($result->value());
        echo "Available Developers:\n";
        foreach ($developers as $name) {
            echo "- $name\n";
        }
    }


    /**
     * Method used to print a confirmation prompt with the current details
     * of the given issue.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   integer $issue_id The issue ID
     */
    function promptConfirmation($rpc_conn, $issue_id)
    {
        // get summary of issue, then show confirmation prompt to user
        $msg = new XML_RPC_Message("getSimpleIssueDetails", array(new XML_RPC_Value($issue_id, "int")));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        } else {
            $details = XML_RPC_decode($result->value());
            $msg = "These are the current details for issue #$issue_id:\n   Summary: " . $details['summary'] . "\nAre you sure you want to change this issue?";
            $ret = Misc::prompt($msg, 'y');
            if (strtolower($ret) != 'y') {
                exit;
            }
        }
    }


    /**
     * Method used to check the authentication of the current user.
     *
     * @access  public
     * @param   resource $rpc_conn The connection resource
     * @param   string $email The email address of the current user
     * @param   string $password The password of the current user
     */
    function checkAuthentication($rpc_conn, $email, $password)
    {
        $msg = new XML_RPC_Message("isValidLogin", array(new XML_RPC_Value($email), new XML_RPC_Value($password)));
        $result = $rpc_conn->send($msg);
        if ($result->faultCode()) {
            Command_Line::quit($result->faultString());
        }
        $is_valid = XML_RPC_Decode($result->value());
        if ($is_valid != 'yes') {
            Command_Line::quit("Login information could not be authenticated");
        }
    }


    /**
     * Method used to check whether the current execution needs to have a 
     * confirmation message shown before performing the requested action or not.
     *
     * @access  public
     * @return  boolean
     */
    function isSafeExecution()
    {
        global $HTTP_SERVER_VARS;

        if ($HTTP_SERVER_VARS['argv'][count($HTTP_SERVER_VARS['argv'])-1] == '--safe') {
            unset($HTTP_SERVER_VARS['argv'][count($HTTP_SERVER_VARS['argv'])-1]);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Method used to print a usage statement for the command line interface.
     *
     * @access  public
     * @param   string $script The current script name
     */
    function usage($script)
    {
        $script = basename($script);
        echo "
General Usage:

1.) $script <ticket_number>
2.) $script <ticket_number> lock [--force] [--safe]
3.) $script <ticket_number> assign <developer_email> [--safe]
4.) $script <ticket_number> set-status <status> [--safe]
5.) $script <ticket_number> add-time <time_worked> [--safe]
6.) $script developers
7.) $script open-issues [my]
8.) $script list-status

Explanations:

1.) View general details of an existing issue.

2.) Lock and assign an issue to yourself. NOTE: You can add keyword '--force' at
    the end if you want to lock an issue even if this issue is already locked
    by someone else.

3.) Assign an issue to another developer.

4.) Sets the status of an issue to the desired value. If you are not sure about
    the available statuses, use command 'list-status' described below.

5.) Records time worked to the time tracking tool of the given issue.

6.) List all available developers' email addresses.

7.) List all issues that are not set to a status with a 'closed' context. Use 
    optional argument 'my' if you just wish to see issues assigned to you.

8.) List all available statuses in the system.
";
        exit;
    }


    /**
     * Method used to print a message to standard output and halt processing.
     *
     * @access  public
     * @param   string $msg The message that needs to be printed
     */
    function quit($msg)
    {
        die("Error - $msg. Run script with --help for usage information.\n");
    }
}


// benchmarking the included file (aka setup time)
if (APP_BENCHMARK) {
    $GLOBALS['bench']->setMarker('Included Command_Line Class');
}
?>