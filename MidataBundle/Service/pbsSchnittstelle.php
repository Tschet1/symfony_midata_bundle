<?php
/**
 * Created by PhpStorm.
 * User: Tschet
 * Date: 10.01.18
 * Time: 21:33
 */

namespace PfadiZytturm\MidataBundle\Service;

use PfadiZytturm\MidataBundle\PfadiZytturmMidataBundle;
use Requests;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    private $role_mapping;
    private $tn_roles;

    /**
     * pbsSchnittstelle constructor.
     * @param ContainerInterface $container container containing the config
     */
    public function __construct(ContainerInterface $container)
    {
        // TODO: enable different forms of caches or allow to disable caches
        $this->cache = new FilesystemCache();
        $this->url = $container->getParameter("midata.url");
        $this->user = $container->getParameter("midata.user");
        $this->password = $container->getParameter("midata.password");
        $this->groupId = $container->getParameter("midata.groupId");
        $this->cacheTTL = $container->getParameter("midata.cache.TTL");
        if ($container->hasParameter('midata.roleMapping')) {
            $tmpmapping = $container->getParameter('midata.roleMapping');
            if (count($tmpmapping) > 0) {
                $this->role_mapping = $tmpmapping;
            }
        }
        $this->tn_roles = $container->getParameter("midata.tnRoles");
    }

    /**
     * User login to midata. This can be used to get a means of log in for your homepage by connecting to midata or
     * loading information about your users.
     * However, you should consider caching the results or have some backup authentification mechanism as the midata
     * is down from time to time of updates could break the bindings.
     *
     * @param string $mail mail that is used to login to midata
     * @param string $password user's password
     * @return array an array with the user configuration:  user: mail used for login, password: the password,
     *      roles: the users roles, stufe: the stufe the user belongs to, Pfadiname: the nickname of the user
     * @throws \Exception on error
     */
    public function getUser($mail, $password)
    {
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
            #$logger->log("No connection to midata");
            throw new \Exception('Keine Verbindung zur Midata möglich... Versuche es doch in ein paar Minuten 
            nochmals oder melde dich beim Webmaster \n' . $e->getMessage());
        }

        // we received an answer, check if the request was successful
        $res = json_decode($raw->body, true);
        if (isset($res['error'])) {
            throw new \Exception('User oder Password falsch.');
        }
        // login successful!
        $pfadiname = $res['people'][0]['nickname'];

        //get token
        $authentication_token = $res['people'][0]['authentication_token'];
        if (!$authentication_token) {
            #$logger->log("No token... " . implode($res));
            throw new \Exception('Token konnte nicht geladen werden.');
        }
        //token ready, password is not needed anymore.

        //add session token to header
        $headers["X-User-Email"] = $mail;
        $headers["X-User-Token"] = $authentication_token;

        /*
         * II: Load information about the user
         */
        $raw = Requests::get($res["people"][0]["href"], $headers);
        $res = json_decode($raw->body, true);

        // Nicht-Pfadi-Zytturm Mitglieder herausfiltern
        $isMember = false;
        if (isset($res['linked']['groups'])) {
            foreach ($res['linked']['groups'] as $group) {
                if ($group['id'] == $this->groupId) {
                    $isMember = true;
                    break;
                }
            }
            if (!$isMember) {
                #$logger->log("Scheinbar kein Mitglied der Abteilung, welches probiert sich hier einzuloggen. ");
                throw new \Exception('Anscheinend gehörst du nicht zur Abteilung...');
            }
        } else {
            #$logger->log("unkonwn error during login");
            throw new \Exception('Strukturfehler');
        }

        // add roles to the user
        $roles = array();
        if ($this->role_mapping !== null) {
            if (isset($res['people'][0]['links']['roles'])) {
                //iterate over roles
                foreach ($res['people'][0]['links']['roles'] as $role) {
                    //get role from id
                    foreach ($res['linked']['roles'] as $tmp) {
                        //role in ids found
                        if ($tmp['id'] == $role) {
                            // check if the user is actually a TN
                            if (in_array($tmp['role_type'], $this->tn_roles)) {
                                // fail, a TN tries to log in!
                                throw new \Exception('Du scheinst ein Teilnehmer zu sein... Zugang nur für Leiter!');
                            }
                            //iterate over mapping
                            foreach ($this->role_mapping as $role_name => $role_array) {
                                if (in_array($tmp['role_type'], $role_array)) {
                                    if (!in_array($role_name, $roles)) {
                                        $roles[] = $role_name;
                                    }
                                }
                            }
                        }
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
                    . "/" . $query . ".json"
                );
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

    /**
     * @param $group integer the group to query
     * @return array list of subgroups
     *
     * Loads sub groups of group (recursively)
     */
    public function getChildren($group = null)
    {
        if ($group === null) {
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
        $list = array(
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
    public function loadMembersOfGroupWithFilter($group, $filter, $inklusiveUntergruppen, $inklusiveAPVundERinUntergruppen = false, $nurVersanAdressen = false, $nurUntergruppen = false, $removeDuplicates = true)
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
                                )
                            );
                        }
                    }
                }

                // remove duplicates
                if ($removeDuplicates) {
                    $this->removeDuplicates($lst, 'id');
                }
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
            if (!isset($temp_array[$v[$key]])) {
                $temp_array[$v[$key]] =& $v;
            }
        }
        $array = array_values($temp_array);
    }
}
