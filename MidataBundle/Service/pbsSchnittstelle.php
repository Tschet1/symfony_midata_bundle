<?php
/**
 * Created by PhpStorm.
 * User: Jan
 * Date: 10.01.18
 * Time: 21:33
 */

namespace PfadiZytturm\MidataBundle\Service;

use PfadiZytturm\MidataBundle\PfadiZytturmMidataBundle;
use Requests;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\DependencyInjection\ContainerInterface;

// TODO: handle dependencies

class pbsSchnittstelle extends PfadiZytturmMidataBundle
{
    //settings PBS DB:
    private $token = null;
    private $tokenExpire = null;
    private $url;
    private $user;
    private $password;
    private $groupId;
    private $cache;
    private $cacheTTL;

    /**
     * pbsSchnittstelle constructor.
     * @param ContainerInterface $container container containing the config
     */
    public function __construct(ContainerInterface $container)
    {

        //TODO: where should the config come from???

        // TODO: enable different forms of caches or allow to disable caches
        $this->cache = new FilesystemCache();
        $this->url = $container->getParameter("midata.url");
        $this->user = $container->getParameter("midata.user");
        $this->password = $container->getParameter("midata.password");
        $this->groupId = $container->getParameter("midata.groupId");
        $this->cacheTTL = $container->getParameter("midata.cache.TTL");
    }

    /**
     * User login to midata
     *
     * @param string $mail mail that is used to login to midata
     * @param string $password user's password
     * @return array //TODO: define
     * @throws \Exception //TODO: define
     */
    public function getUser($mail, $password)
    {
        //TODO: this function
        /*
         * I: get a session token from midata
         */

        //create request
        $headers = array("Accept" => "application/json");
        $data = array("person[email]" => $mail, "person[password]" => $password);

        try {
            //send request
            $raw = Requests::post($this->url . "/users/token", $headers, $data, ['timeout' => 50]);
        } catch (\Exception $e) {
            throw new \Exception('Keine Verbindung zur Midata möglich... Versuche es doch in ein paar Minuten nochmals oder melde dich beim Webmaster \n' . $e->getMessage());
        }

        // we received an answer, check if the request was successful
        $res = json_decode($raw->body, true);
        if (isset($res['error'])) {
            // TODO: how should this be handled?
            throw new \Exception('User oder Password falsch.');
        }
        // login successful!

        $pfadiname = $res['people'][0]['nickname'];

        //get token
        $authentication_token = $res['people'][0]['authentication_token'];
        if (!$authentication_token) {
            throw new \Exception('Token konnte nicht geladen werden.');
        }
        //token ready, password is not needed anymore.

        //add session token to header
        $headers["X-User-Email"] = $mail;
        $headers["X-User-Token"] = $authentication_token;

        /*
         * II: Load information about the user
         * //TODO: move this out of this class?
         */
        $raw = Requests::get($res["people"][0]["href"], $headers);
        $res = json_decode($raw->body, true);

        // TODO: should this be based on the group number?
        // Nicht-Pfadi-Zytturm Mitglieder herausfiltern
        $isMember = false;
        if (isset($res['linked']['groups'])) {
            foreach ($res['linked']['groups'] as $group) {
                // TODO: change this to id
                if ($group['name'] == 'Pfadi Zytturm') {
                    $isMember = true;
                    break;
                }
            }
            if (!$isMember) {
                throw new \Exception('Anscheinend gehörst du nicht zur Pfadi Zytturm....');
            }
        } else {
            throw new \Exception('Strukturfehler');
        }


        //todo: export to parameters
        $acceptedRolesLeiter = ['Einheitsleiter', 'Mitleiter', 'Adressverwalter'];
        $acceptedRolesStufenleiter = ['Stufenleiter Biber', 'Stufenleiter Wölfe', 'Stufenleiter Pfadi', 'Stufenleiter Pio', 'Stufenleiter Rover'];
        $acceptedRolesAbteilungsleitung = ['Coach', 'Abteilungsleiter', 'Präsident'];
        $acceptedRolesHeime = [];
        $acceptedRolesWebmaster = ['Webmaster'];

        $roles = Array();
        if (isset($res['people'][0]['links']['roles'])) {
            //iterate over roles
            foreach ($res['people'][0]['links']['roles'] as $role) {
                //get role from id
                foreach ($res['linked']['roles'] as $tmp) {
                    //role in ids found
                    if ($tmp['id'] == $role) {
                        if (in_array($tmp['role_type'], $acceptedRolesLeiter)) {
                            $r = "ROLE_LEITER";
                        } elseif (in_array($tmp['role_type'], $acceptedRolesStufenleiter)) {
                            $r = "ROLE_STUFENLEITER";
                        } elseif (in_array($tmp['role_type'], $acceptedRolesAbteilungsleitung)) {
                            $r = "ROLE_ABTEILUNGSLEITUNG";
                        } elseif (in_array($tmp['role_type'], $acceptedRolesHeime)) {
                            $r = "ROLE_HEIMVERWALTUNG";
                        } elseif (in_array($tmp['role_type'], $acceptedRolesWebmaster)) {
                            $r = "ROLE_WEBMASTER";
                        } else {
                            break 1;
                        }

                        if (!in_array($r, $roles)) {
                            $roles[] = $r;
                        }

                        break 1;
                    }
                }
            }
        }

        //get stufe
        $stufe = 'Rover';
        if (isset($res['linked']['groups'])) {
            foreach ($res['linked']['groups'] as $group) {
                if (in_array($group['group_type'], ['Biber', 'Wölfe', 'Pfadi', 'Pios'])) {
                    $stufe = $group['group_type'];
                    break;
                }
            }
        }

        return array(
            "user" => $mail,
            "password" => $password,
            "roles" => $roles,
            "stufe" => $stufe,
            "Pfadiname" => $pfadiname
        );

    }

    /**
     * @param $group integer Group id as given in midata.
     * @return mixed|null people that are part of the given group. Null if there is an error.
     */
    public function requestGroupMembers($group)
    {
        return $this->queryWrap('groups/' . $group . '/people');
    }

    /**
     * @param $group integer Group id as given in midata.
     * @return mixed|null The information about the group.
     */
    public function requestGroup($group)
    {
        return $this->queryWrap('groups/' . $group);
    }

    /**
     * Use this function to cache your queries.
     *
     * @param $query string The query to be executed
     * @return mixed|null Result of query.
     * @throws \Exception throws an exception if an error occurs.
     */
    public function queryWrap($query)
    {
        // Check if query is cached
        $cacheKey = "pbsschnittstelle" . str_replace("/", "", $query);
        if ($this->cache->has($cacheKey)) {
            // found in cache, serve from cache
            return $this->cache->get($cacheKey);
        } else {
            try {
                // do the actual querry at midata
                $ret = $this->doQuery(
                    $this->url
                    . "/" . $query . ".json");

            } catch (\Exception $e) {
                throw $e;
            }

            // set timeout for new cache value
            $this->cache->set($cacheKey, $ret, $this->cacheTTL);
            return $ret;
        }
    }

    /**
     * @param $query string query to be executed
     * @return mixed return value
     * @throws \Exception
     *
     * Perform the actual query
     */
    public function doQuery($query)
    {
        $headers = array("Accept" => "application/json");

        // check if we are already authenticated
        if (!$this->token || $this->tokenExpire > date_create()) {
            // no token or token expired, authenticate
            // assemble the headers (user, pw)
            $data = array(
                "person[email]" => $this->user,
                "person[password]" => $this->password
            );

            try {
                //send request
                $raw = Requests::post(
                    $this->url . "/users/token",
                    $headers,
                    $data
                );
            } catch (\Exception $e) {
                throw new \Exception('Keine Verbindung zur Midata möglich oder sonstiger Fehler beim Request.');
            }

            //got anser, decode it
            $res = json_decode($raw->body, true);

            // we got authentication token, store and proceed
            $this->token = $res['people'][0]['authentication_token'];
            $this->tokenExpire = date_create_from_format("Y-m-d*H:i:s.uP", $res['people'][0]['current_sign_in_at']);
            if (!$this->token) {
                throw new \Exception('Token konnte nicht geladen werden.');
            }
        }

        // set the headers
        $headers["X-User-Email"] = $this->user;
        $headers["X-User-Token"] = $this->token;

        // perform the actual query
        $raw = Requests::get($query, $headers);
        if (!$raw->success) {
            throw new \Exception('Fehler bei Kommunikation mit Midata (query)');
        }

        // return answer as array
        $res = json_decode($raw->body, true);
        return $res;
    }


















    /*
     * TODO: delete
     */
    /*
    public function getStuLei($stufe)
    {
        $stufenname = null;

        switch ($stufe) {
            case 0:
                $stufenname = "Stufenleiter Biber";
                break;
            case 1:
                $stufenname = "Stufenleiter Wölfe";
                break;
            case 2:
                $stufenname = "Stufenleiter Pfadi";
                break;
            case 3:
                $stufenname = "Stufenleiter Pio";
                break;
            case 4:
                $stufenname = "Stufenleiter Rover";
                break;

        }

        $persons = $this->requestGroupMembers(pbsSchnittstelle::$mainGroupID);

        if (!$persons) {
            return [];
        }

        $id = [];
        foreach ($persons['linked']['roles'] as $role) {
            if ($role['role_type'] == $stufenname) {
                $id[] = $role['id'];
            }
        }

        if (count($id) == 0) {
            return [];
        }

        $stulei = [];
        foreach ($persons['people'] as $person) {
            if (count(array_intersect($id, $person['links']['roles']))) {
                $stulei[] = $person['href'];

            }
        }


        $stufenleiter_expanded = [];
        foreach ($stulei as $stu) {
            $tmp = $this->doQuery($stu);

            $t['Vorname'] = $tmp['people'][0]['first_name'];
            $t['Nachname'] = $tmp['people'][0]['last_name'];
            $t['Pfadiname'] = $tmp['people'][0]['nickname'];
            $t['Mail'] = $tmp['people'][0]['email'];
            $t['Info'] = $tmp['people'][0]['additional_information'];

            $stufenleiter_expanded[$t['Pfadiname']] = $t;

        }

        return $stufenleiter_expanded;


    }
    */

    /**
     * @param $group integer the group to query
     * @return array list of subgroups
     *
     * Loads sub groups of group (recursively)
     */
    public function getChildren($group = null)
    {
        if($group === null){
            $group = $this->groupId;
        }

        // query the group
        $groupDetails = $this->requestGroup($group);

        if (!$groupDetails || !isset($groupDetails['groups'])) {
            // there must have been an error
            // return an empty array
            return array();
        }

        // make a nice list of all children
        $list = Array(
            'name' => $groupDetails['groups'][0]['name'],
        );
        if (isset($groupDetails['groups'][0]['links']['children'])) {
            foreach ($groupDetails['groups'][0]['links']['children'] as $g) {
                // recurse
                $list['Untergruppen'][$g] = $this->getChildren($g);
            }
        }
        return $list;
    }

    /**
     * @param $group integer group id
     * @param $filter "Alle"|"Leiter"|"Teilnehmer"
     * @param bool $inklusiveUntergruppen set true if the function should recurse into sub groups
     * @param bool $inklusiveAPVundERinUntergruppen should the APV and ER groups be included
     * @param bool $nurVersanAdressen should the non delivery mail addresses be filtered out?
     * @param bool $nurUntergruppen skip the group itself and continue with the children of the group
     * @return array result of person queries of matching persons
     *
     * Search for users.
     */
    public function loadMembersOfGroupWithFilter($group, $filter, $inklusiveUntergruppen, $inklusiveAPVundERinUntergruppen = false, $nurVersanAdressen = false, $nurUntergruppen = false)
    {
        // return list
        $lst = array();

        // get the list of members of the current group
        $memberlist = $this->requestGroupMembers($group, $filter);


        $mailadressen = array();
        $telefonNummern = array();
        $mask = array();
        $rolesList = array();
        $groupsList = array();

        // define the roles that are used by TNs
        $tnRollen = ['Biber', 'Wolf', 'Leitwolf', 'Pfadi', 'Leitpfadi', 'Pio', 'Mitglied'];

        try {
            // return if list is empty
            if (count($memberlist['people']) == 0) {
                return array();
            }

            // list the child groups
            foreach ($memberlist['linked']['groups'] as $g) {
                $groupsList[$g["id"]] = $g["name"];
            }

            // do the role based filtering
            foreach ($memberlist['linked']['roles'] as $role) {
                $mask[$role['id']] = (
                    $filter == 'Alle' or
                    $filter == '' or
                    (
                        $filter == 'Teilnehmer' and
                        in_array($role['role_type'], $tnRollen)
                    ) or (
                        $filter == 'Leiter' and
                        !in_array($role['role_type'], $tnRollen)
                    ) or (
                        strpos($role['role_type'], $filter) !== false
                    )
                );

                $rolesList[$role["id"]]["Rolle"] = $role["role_type"];
                $rolesList[$role["id"]]["Label"] = $role["label"];
                $rolesList[$role["id"]]["Gruppe"] = $groupsList[$role["links"]["group"]];
            }

            // resolve the links for mail addresses and phone numbers
            if (isset($memberlist['linked']['phone_numbers'])) {
                foreach ($memberlist['linked']['phone_numbers'] as $num) {
                    $telefonNummern[$num['id']] = array(
                        'label' => $num['label'],
                        'nummer' => $num['number']
                    );
                }
            } else {
                $telefonNummern = array();
            }

            if (isset($memberlist['linked']['additional_emails'])) {
                foreach ($memberlist['linked']['additional_emails'] as $adr) {
                    $mailadressen[$adr['id']] = array(
                        'label' => $adr['label'],
                        'mail' => $adr['email'],
                        'versand' => $adr['mailings']
                    );
                }
            } else {
                $mailadressen = array();
            }

            // sometimes we want to skip the class itself
            if (!$nurUntergruppen) {
                // asemble the output list
                foreach ($memberlist['people'] as $member) {
                    $filtered = false;
                    foreach ($member['links']['roles'] as $role) {
                        if ($mask[$role]) {
                            $filtered = true;
                            $member["Rollen"] = $rolesList[$role];
                            break;
                        }
                    }

                    if (!$filtered) {
                        continue;
                    }

                    $member['Versandadressen'] = array();
                    if (isset($member['links']['additional_emails'])) {
                        foreach ($member['links']['additional_emails'] as $mail) {
                            $member["e" . $mailadressen[$mail]['label']] = $mailadressen[$mail]['mail'];
                            if ($mailadressen[$mail]['versand']) {
                                $member['Versandadressen'][] = $mailadressen[$mail]['mail'];
                            }
                        }
                    }

                    if ($member['email']) {
                        $member['Versandadressen'][] = $member['email'];
                    }

                    if (isset($member['links']['phone_numbers'])) {
                        foreach ($member['links']['phone_numbers'] as $telefon) {
                            $member["t" . $telefonNummern[$telefon]['label']] = $telefonNummern[$telefon]['nummer'];
                        }
                    }

                    $lst[] = $member;
                }
            }

            // search child groups (recurse)
            if ($inklusiveUntergruppen) {
                $groupDetails = $this->requestGroup($group);
                if (isset($groupDetails['groups']) && isset($groupDetails['groups'][0]['links']) &&
                    isset($groupDetails['groups'][0]['links']['children'])) {
                    foreach ($groupDetails['groups'][0]['links']['children'] as $subgroup) {
                        # sende dem APV und dem Elternrat keine Mail, wenn alle ausgewählt sind!.
                        if ($inklusiveAPVundERinUntergruppen || ($subgroup != "6053" && $subgroup != "6054")) {
                            $lst = array_merge(
                                $lst,
                                $this->loadMembersOfGroupWithFilter(
                                    $subgroup,
                                    $filter,
                                    $inklusiveUntergruppen,
                                    $inklusiveAPVundERinUntergruppen,
                                    $nurVersanAdressen,
                                    false
                                ));
                        }
                    }
                }

                // remove duplicates
                $this->removeDuplicates($lst, 'id');
            }
            return $lst;
        } catch (\Exception $e) {
            return array();
        }
    }

    /**
     * @param mixed $array array to search
     * @param string $key key that identifies unique users
     */
    private function removeDuplicates(&$array, $key)
    {
        $temp_array = array();
        foreach ($array as &$v) {

            if (!isset($temp_array[$v[$key]]))

                $temp_array[$v[$key]] =& $v;

        }
        $array = array_values($temp_array);


    }
}