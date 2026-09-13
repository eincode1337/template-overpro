<?php

namespace app\modules\module_page_skins\system;

use app\modules\module_page_skins\system\FunctionCore;

class Agents extends FunctionCore
{
    protected $Db, $General, $Translate, $languages, $agents;

    public function __construct($Db, $General, $Translate)
    {
        $this->Db        = $Db;
        $this->General   = $General;
        $this->Translate = $Translate;
        $this->languages = strtolower($_SESSION['language']);

        $file_exists   = realpath($_SERVER['DOCUMENT_ROOT'] . "/app/modules/module_page_skins/cache/agents-{$this->languages}.json");
        $agentsFile    = MODULES . "module_page_skins/cache/agents-{$this->languages}.json";

        $this->agents  = file_exists($file_exists) ? $agentsFile : MODULES . "module_page_skins/cache/agents-ru.json";
    }

    private function DataDBUser()
    {

        $steamid = $_SESSION['steamid64'];

        if ($this->Settings('type') == 1 || $this->Settings('type') == 3) {
            return $steamid;
        }

        $user = $this->Db->query('Skins', 0, 0, "SELECT `id` FROM `sc_player` WHERE `steamid` = :steamid LIMIT 1", [
            'steamid' => $steamid
        ]);

        if (isset($user['id'])) {
            return $user['id'];
        }

        $this->insertUser($steamid);
        $user = $this->Db->query('Skins', 0, 0, "SELECT `id` FROM `sc_player` WHERE `steamid` = :steamid LIMIT 1", [
            'steamid' => $steamid
        ]);

        return $user['id'];
    }

    private function insertUser($steamid)
    {
        $name = $this->General->checkName($steamid);
        $this->Db->query('Skins', 0, 0, "INSERT INTO `sc_player` (`name`, `steamid`) VALUES (:name, :steamid)", [
            'name' => !empty($name) ? $name : "Unknown",
            'steamid' => $steamid
        ]);
    }

    public function IntefaceAgents($server = 0, $team = 0, $agent = null)
    {

        if (!is_numeric($server) || intval($server) != $server || $server < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $server >= $this->Db->table_count['Skins']) return ['html' => $this->Translate('_nonono')];
        if (!is_numeric($team) || intval($team) != $team || $team > 2 || $team < 0) return ['html' => $this->Translate('_nonono')];
        if (!isset($_SESSION['steamid64'])) {
            return ['html' => $this->Translate('_no_auth')];
        }

        $html = "";
        $ID_user_items = ['agent' => null];
        $Agents = $this->get_cache($this->agents);
        $settingsType = $this->Settings('type');
        $module_status = ($settingsType == 3) ? $this->ModuleStatus() : null;

        if (!empty($this->DataDBUser())) {
            if ($this->Settings('type') == 1) {
                if ($team == 0) {
                    $Name_user_items = $this->Db->query('Skins', $server, 0, "SELECT `agent_t` AS `agent` FROM `wp_player_agents` WHERE `steamid` = :player_id LIMIT 1", [
                        'player_id' => $this->DataDBUser()
                    ]);
                } else if ($team == 1) {
                    $Name_user_items = $this->Db->query('Skins', $server, 0, "SELECT `agent_ct` AS `agent` FROM `wp_player_agents` WHERE `steamid` = :player_id LIMIT 1", [
                        'player_id' => $this->DataDBUser()
                    ]);
                }
                $AgentsID = array_filter($Agents, function ($skin) use ($Name_user_items) {
                    return $skin['model'] == $Name_user_items['agent'];
                });
                $AgentsID = reset($AgentsID);
                $ID_user_items['agent'] = $AgentsID['id'];
            } else if ($this->Settings('type') == 2) {
                $ID_user_items = $this->Db->query('Skins', 0, 0, "SELECT `agent` FROM `sc_items` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                    'player_id' => $this->DataDBUser(),
                    'server_id' => $server,
                    'team' => $team,
                ]);
            } else if ($this->Settings('type') == 3) {
                if ($team == 0) {
                    $Name_user_items = $this->Db->query('Skins', $server, 0, "SELECT `agent_t` AS `agent` FROM `wp_agents` WHERE `steamid` = :player_id LIMIT 1", [
                        'player_id' => $this->DataDBUser()
                    ]);
                } else if ($team == 1) {
                    $Name_user_items = $this->Db->query('Skins', $server, 0, "SELECT `agent_ct` AS `agent` FROM `wp_agents` WHERE `steamid` = :player_id LIMIT 1", [
                        'player_id' => $this->DataDBUser()
                    ]);
                }
                $AgentsID = array_filter($Agents, function ($skin) use ($Name_user_items) {
                    return $skin['model'] == $Name_user_items['agent'];
                });
                $AgentsID = reset($AgentsID);
                $ID_user_items['agent'] = $AgentsID['id'];
            }
        }

        $Agents = array_filter($Agents, function ($skin) use ($team) {
            return $skin['team'] == $team;
        });

        if (!empty($agent)) {
            $Agents = array_filter($Agents, function ($skin) use ($agent) {
                return strpos(strtolower($skin['name']), strtolower($agent)) !== false;
            });
        }

        $rarityOrder = [
            'rarity_default' => 1,
            'rarity_uncommon_character' => 2,
            'rarity_rare_character' => 3,
            'rarity_mythical_character' => 4,
            'rarity_legendary_character' => 5,
            'rarity_ancient_character' => 6,
            'rarity_contraband_character' => 7,
        ];

        usort($Agents, function ($a, $b) use ($rarityOrder) {
            $aOrder = $rarityOrder[$a['id_rarity']];
            $bOrder = $rarityOrder[$b['id_rarity']];

            return $aOrder - $bOrder;
        });

        foreach ($Agents as $Agent) {

            $id = $Agent['id'];
            $name = $Agent['name'];
            $img = $Agent['image'];
            $desc = $Agent['rarity'];
            $rarity = ' ' . $Agent['id_rarity'];

            $choice_active = "";
            $choose_weapon = $this->Translate('_choose_weapon');
            if ($ID_user_items['agent'] == $id) {
                $choice_active = " choice_active";
                $choose_weapon = $this->Translate('_choose_default');
            }

            $disabled_class = '';
            if ($settingsType == 3 && isset($module_status['wp_agents']) && $module_status['wp_agents'] == 0) {
                $disabled_class = ' disabled_button';
            }

            $html .= <<<HTML
                            <div class="block-skin-fon{$choice_active}{$rarity}{$disabled_class}" id="set_agent" id_agent="{$id}">
                                <b class="button-skin">{$choose_weapon}</b>
                                <div class="block-skin-img">
                                    <img class="loader_img_all" src="{$img}" loading="lazy" alt="{$name}">
                                </div>
                                <div class="block-skin-info">
                                    <b>{$name}</b>
                                    <span>{$desc}</span>
                                </div>
                            </div>
                         HTML;
        }

        return ['html' => $html ?: $this->Translate('_skins_no')];
    }

    public function SCSkinChangerAgents(int $type = 1, $server = 0, $team = 0, int $agent = 0)
    {

        if (!is_numeric($server) || intval($server) != $server || $server < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $server >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($team) || intval($team) != $team || $team > 2 || $team < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];

        if ($this->TableSearch()) return ['status' => 'error', 'text' => $this->Translate('_adm_table_no')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];

        $Agents = $this->get_cache($this->agents);
        $Agents = array_filter($Agents, function ($skin) use ($agent) {
            return $skin['id'] == $agent;
        });
        $Agents = reset($Agents);
        if (empty($Agents['team']) && $Agents['team'] != $team) return ['status' => 'error', 'text' => $this->Translate('_error3')];

        if (empty($this->DataDBUser())) return ['status' => 'error', 'text' => $this->Translate('_error1')];

        if ($this->Settings('type') == 1) {
            $ID_user_items = $this->Db->query('Skins', $server, 0, "SELECT `agent_ct`, `agent_t` FROM `wp_player_agents` WHERE `steamid` = :player_id LIMIT 1", [
                'player_id' => $this->DataDBUser()
            ]);
        } else if ($this->Settings('type') == 2) {
            $ID_user_items = $this->Db->query('Skins', 0, 0, "SELECT `id`, `agent` FROM `sc_items` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $server,
                'team' => $team
            ]);
        } else if ($this->Settings('type') == 3) {
            $ID_user_items = $this->Db->query('Skins', $server, 0, "SELECT `agent_ct`, `agent_t` FROM `wp_agents` WHERE `steamid` = :player_id LIMIT 1", [
                'player_id' => $this->DataDBUser()
            ]);
        }

        switch ($type) {
            case 0:
                if ($this->Settings('type') == 1) {
                    if (!empty($ID_user_items)) {
                        if ($team == 0) {
                            $this->Db->query('Skins', $server, 0, "UPDATE `wp_player_agents` SET `agent_t` = :agent_t WHERE `steamid` = :id LIMIT 1", [
                                "id" => $this->DataDBUser(),
                                "agent_t" => $Agents['model'],
                            ]);
                        } else if ($team == 1) {
                            $this->Db->query('Skins', $server, 0, "UPDATE `wp_player_agents` SET `agent_ct` = :agent_t WHERE `steamid` = :id LIMIT 1", [
                                "id" => $this->DataDBUser(),
                                "agent_t" => $Agents['model'],
                            ]);
                        }
                        return ['status' => 'success', 'text' => $this->Translate('_ag_change')];
                    } else {
                        if ($team == 0) {
                            $query = "INSERT INTO `wp_player_agents`(`steamid`, `agent_t`, `agent_ct`) 
                                VALUES (:id, :agent, NULL)";
                        } else if ($team == 1) {
                            $query = "INSERT INTO `wp_player_agents`(`steamid`, `agent_t`, `agent_ct`) 
                            VALUES (:id, NULL, :agent)";
                        }
                        $this->Db->query('Skins', $server, 0, $query, [
                            "id" => $this->DataDBUser(),
                            "agent" => $Agents['model'],
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_ag_change')];
                    }
                } else if ($this->Settings('type') == 2) {
                    if (!empty($ID_user_items)) {
                        $this->Db->query('Skins', 0, 0, "UPDATE `sc_items` SET `agent` = :agent WHERE `id` = :id LIMIT 1", [
                            "id" => $ID_user_items['id'],
                            "agent" => $Agents['id']
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_ag_change')];
                    } else {
                        $query = "INSERT INTO `sc_items`(`player_id`, `server_id`, `team`, `agent`, `music`, `coin`, `knife`, `glove`) 
                            VALUES (:player_id, :server_id, :team, :agent, 0, 0, 0, 0)";
                        $this->Db->query('Skins', 0, 0, $query, [
                            "player_id" => $this->DataDBUser(),
                            "server_id" => $server,
                            "team" => $team,
                            "agent" => $Agents['id'],
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_ag_change')];
                    }
                } else if ($this->Settings('type') == 3) {
                    if (!empty($ID_user_items)) {
                        if ($team == 0) {
                            $this->Db->query('Skins', $server, 0, "UPDATE `wp_agents` SET `agent_t` = :agent_t WHERE `steamid` = :id LIMIT 1", [
                                "id" => $this->DataDBUser(),
                                "agent_t" => $Agents['model'],
                            ]);
                        } else if ($team == 1) {
                            $this->Db->query('Skins', $server, 0, "UPDATE `wp_agents` SET `agent_ct` = :agent_ct WHERE `steamid` = :id LIMIT 1", [
                                "id" => $this->DataDBUser(),
                                "agent_ct" => $Agents['model'],
                            ]);
                        }
                        return ['status' => 'success', 'text' => $this->Translate('_ag_change')];
                    } else {
                        if ($team == 0) {
                            $query = "INSERT INTO `wp_agents`(`steamid`, `agent_t`, `agent_ct`) VALUES (:id, :agent, NULL)";
                        } else if ($team == 1) {
                            $query = "INSERT INTO `wp_agents`(`steamid`, `agent_t`, `agent_ct`) VALUES (:id, NULL, :agent)";
                        }
                        $this->Db->query('Skins', $server, 0, $query, [
                            "id" => $this->DataDBUser(),
                            "agent" => $Agents['model'],
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_ag_change')];
                    }
                }
            case 1:
                if ($this->Settings('type') == 1) {
                    if (!empty($ID_user_items)) {
                        if ($team == 0) {
                            $this->Db->query('Skins', $server, 0, "UPDATE `wp_player_agents` SET `agent_t` = NULL WHERE `steamid` = :id LIMIT 1", [
                                "id" => $this->DataDBUser()
                            ]);
                        } else if ($team == 1) {
                            $this->Db->query('Skins', $server, 0, "UPDATE `wp_player_agents` SET `agent_ct` = NULL WHERE `steamid` = :id LIMIT 1", [
                                "id" => $this->DataDBUser()
                            ]);
                        }
                        return ['status' => 'success', 'text' => $this->Translate('_ag_del')];
                    } else {
                        return ['status' => 'success', 'text' => $this->Translate('_sag_no')];
                    }
                } else if ($this->Settings('type') == 2) {
                    if (!empty($ID_user_items)) {
                        $this->Db->query('Skins', 0, 0, "UPDATE `sc_items` SET `agent` = '0' WHERE `id` = :id LIMIT 1", [
                            "id" => $ID_user_items['id']
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_ag_del')];
                    } else {
                        return ['status' => 'success', 'text' => $this->Translate('_sag_no')];
                    }
                } else if ($this->Settings('type') == 3) {
                    if (!empty($ID_user_items)) {
                        if ($team == 0) {
                            $this->Db->query('Skins', $server, 0, "UPDATE `wp_agents` SET `agent_t` = NULL WHERE `steamid` = :id LIMIT 1", [
                                "id" => $this->DataDBUser()
                            ]);
                        } else if ($team == 1) {
                            $this->Db->query('Skins', $server, 0, "UPDATE `wp_agents` SET `agent_ct` = NULL WHERE `steamid` = :id LIMIT 1", [
                                "id" => $this->DataDBUser()
                            ]);
                        }
                        return ['status' => 'success', 'text' => $this->Translate('_ag_del')];
                    } else {
                        return ['status' => 'success', 'text' => $this->Translate('_sag_no')];
                    }
                }
            default:
                return ['status' => 'error', 'text' => $this->Translate('_error2')];
        }
    }
}
