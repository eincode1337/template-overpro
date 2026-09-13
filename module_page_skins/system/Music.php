<?php

namespace app\modules\module_page_skins\system;

use app\modules\module_page_skins\system\FunctionCore;

class Music extends FunctionCore
{
    protected $Db, $General, $Translate, $languages, $music;

    public function __construct($Db, $General, $Translate)
    {
        $this->Db        = $Db;
        $this->General   = $General;
        $this->Translate = $Translate;
        $this->languages = strtolower($_SESSION['language']);

        $file_exists     = realpath($_SERVER['DOCUMENT_ROOT'] . "/app/modules/module_page_skins/cache/music-{$this->languages}.json");
        $musicFile       = MODULES . "module_page_skins/cache/music-{$this->languages}.json";

        $this->music     = file_exists($file_exists) ? $musicFile : MODULES . "module_page_skins/cache/music-ru.json";
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

    public function IntefaceMusic($server = 0, $team = 0, $music = null)
    {
        if (!is_numeric($server) || intval($server) != $server || $server < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $server >= $this->Db->table_count['Skins']) return ['html' => $this->Translate('_nonono')];
        if (!is_numeric($team) || intval($team) != $team || $team > 2 || $team < 0) return ['html' => $this->Translate('_nonono')];
        if (!isset($_SESSION['steamid64'])) {
            return ['html' => $this->Translate('_no_auth')];
        }

        $html = "";
        $ID_user_items = ['music' => null];
        $Music = $this->get_cache($this->music);
        $settingsType = $this->Settings('type');
        $module_status = ($settingsType == 3) ? $this->ModuleStatus() : null;

        if (!empty($this->DataDBUser())) {
            if ($this->Settings('type') == 1) {
                $teamwp = empty($team) ? 2 : 3;
                $ID_user_items = $this->Db->query('Skins', $server, 0, "SELECT `music_id` AS `music` FROM `wp_player_music` WHERE `steamid` = :player_id AND `weapon_team` = :weapon_team LIMIT 1", [
                    'player_id' => $this->DataDBUser(),
                    'weapon_team' => $teamwp,
                ]);
            } else if ($this->Settings('type') == 2) {
                $ID_user_items = $this->Db->query('Skins', 0, 0, "SELECT `music` FROM `sc_items` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                    'player_id' => $this->DataDBUser(),
                    'team' => $team,
                    'server_id' => $server,
                ]);
            } else if ($this->Settings('type') == 3) {
                $teamwp = empty($team) ? 2 : 3;
                $ID_user_items = $this->Db->query('Skins', $server, 0, "SELECT `music_id` AS `music` FROM `wp_music` WHERE `steamid` = :player_id AND `weapon_team` = :weapon_team LIMIT 1", [
                    'player_id' => $this->DataDBUser(),
                    'weapon_team' => $teamwp,
                ]);
            }
        }

        if (!empty($music)) {
            $Music = array_filter($Music, function ($skin) use ($music) {
                return strpos(strtolower($skin['name']), strtolower($music)) !== false;
            });
        }

        foreach ($Music as $Mus) {

            $id = $Mus['id'];
            $name = $Mus['name'];
            $img = $Mus['image'];
            $desc = $Mus['rarity'];
            $rarity = ' ' . $Mus['id_rarity'];

            $choice_active = "";
            $choose_weapon = $this->Translate('_choose_weapon');
            if ($ID_user_items['music'] == $id) {
                $choice_active = " choice_active";
                $choose_weapon = $this->Translate('_choose_default');
            }

            $disabled_class = '';
            if ($settingsType == 3 && isset($module_status['wp_music']) && $module_status['wp_music'] == 0) {
                $disabled_class = ' disabled_button';
            }

            $html .= <<<HTML
                            <div class="block-skin-fon{$choice_active}{$rarity}{$disabled_class}" id="set_music" id_music="{$id}">
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

    public function SCSkinChangerMusic(int $type = 2, $server = 0, $team = 0, int $music = 0)
    {

        if (!is_numeric($server) || intval($server) != $server || $server < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $server >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($team) || intval($team) != $team || $team > 2 || $team < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];

        if ($this->TableSearch()) return ['status' => 'error', 'text' => $this->Translate('_adm_table_no')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];

        $Music = $this->get_cache($this->music);
        $Music = array_filter($Music, function ($skin) use ($music) {
            return $skin['id'] == $music;
        });
        $Music = reset($Music);
        if (empty($Music['id']) && $Music['id'] != $music) return ['status' => 'error', 'text' => $this->Translate('_error3')];

        if (empty($this->DataDBUser())) return ['status' => 'error', 'text' => $this->Translate('_error1')];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($team) ? 2 : 3;
            $ID_user_items = $this->Db->query('Skins', 0, 0, "SELECT `music_id` FROM `wp_player_music` WHERE `steamid` = :steamid AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_team' => $teamwp,
            ]);
        } else if ($this->Settings('type') == 2) {
            $ID_user_items = $this->Db->query('Skins', 0, 0, "SELECT `id` FROM `sc_items` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $server,
                'team' => $team,
            ]);
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($team) ? 2 : 3;
            $ID_user_items = $this->Db->query('Skins', 0, 0, "SELECT `music_id` FROM `wp_music` WHERE `steamid` = :steamid AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_team' => $teamwp,
            ]);
        }

        switch ($type) {
            case 0:
                if ($this->Settings('type') == 1) {
                    $teamwp = empty($team) ? 2 : 3;
                    if (!empty($ID_user_items)) {
                        $this->Db->query('Skins', $server, 0, "UPDATE `wp_player_music` SET `music_id` = :music WHERE `steamid` = :id AND `weapon_team` = :weapon_team LIMIT 1", [
                            "id" => $this->DataDBUser(),
                            "music" => $Music['id'],
                            'weapon_team' => $teamwp,
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_ch')];
                    } else {
                        $query = "INSERT INTO `wp_player_music`(`steamid`, `weapon_team`, `music_id`) 
                        VALUES (:id, :weapon_team, :music)";
                        $this->Db->query('Skins', $server, 0, $query, [
                            "id" => $this->DataDBUser(),
                            "music" => $Music['id'],
                            'weapon_team' => $teamwp,
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_ch')];
                    }
                } else if ($this->Settings('type') == 2) {
                    if (!empty($ID_user_items)) {
                        $this->Db->query('Skins', 0, 0, "UPDATE `sc_items` SET `music` = :music WHERE `id` = :id LIMIT 1", [
                            "id" => $ID_user_items['id'],
                            "music" => $Music['id']
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_ch')];
                    } else {
                        $query = "INSERT INTO `sc_items`(`player_id`, `server_id`, `team`, `agent`, `music`, `coin`, `knife`, `glove`) 
                            VALUES (:player_id, :server_id, :team, 0, :music, 0, 0, 0)";
                        $this->Db->query('Skins', 0, 0, $query, [
                            'player_id' => $this->DataDBUser(),
                            'server_id' => $server,
                            'team' => $team,
                            'music' => $Music['id'],
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_ch')];
                    }
                } else if ($this->Settings('type') == 3) {
                    $teamwp = empty($team) ? 2 : 3;
                    if (!empty($ID_user_items)) {
                        $this->Db->query('Skins', $server, 0, "UPDATE `wp_music` SET `music_id` = :music WHERE `steamid` = :id AND `weapon_team` = :weapon_team LIMIT 1", [
                            "id" => $this->DataDBUser(),
                            "music" => $Music['id'],
                            'weapon_team' => $teamwp,
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_ch')];
                    } else {
                        $query = "INSERT INTO `wp_music`(`steamid`, `weapon_team`, `music_id`) VALUES (:id, :weapon_team, :music)";
                        $this->Db->query('Skins', $server, 0, $query, [
                            "id" => $this->DataDBUser(),
                            "music" => $Music['id'],
                            'weapon_team' => $teamwp,
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_ch')];
                    }
                }
            case 1:
                if ($this->Settings('type') == 1) {
                    if (!empty($ID_user_items)) {
                        $teamwp = empty($team) ? 2 : 3;
                        $this->Db->query('Skins', $server, 0, "UPDATE `wp_player_music` SET `music_id` = NULL WHERE `steamid` = :id AND `weapon_team` = :weapon_team LIMIT 1", [
                            "id" => $this->DataDBUser(),
                            "weapon_team" => $teamwp,
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_del')];
                    } else {
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_del_all')];
                    }
                } else if ($_POST['type'] == 2) {
                    if (!empty($ID_user_items)) {
                        $this->Db->query('Skins', 0, 0, "UPDATE `sc_items` SET `music` = '0' WHERE `id` = :id LIMIT 1", [
                            "id" => $ID_user_items['id']
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_del')];
                    } else {
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_del_all')];
                    }
                } else if ($this->Settings('type') == 3) {
                    if (!empty($ID_user_items)) {
                        $teamwp = empty($team) ? 2 : 3;
                        $this->Db->query('Skins', $server, 0, "UPDATE `wp_music` SET `music_id` = NULL WHERE `steamid` = :id AND `weapon_team` = :weapon_team LIMIT 1", [
                            "id" => $this->DataDBUser(),
                            "weapon_team" => $teamwp,
                        ]);
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_del')];
                    } else {
                        return ['status' => 'success', 'text' => $this->Translate('_mkit_del_all')];
                    }
                }
            default:
                return ['status' => 'error', 'text' => $this->Translate('_error2')];
        }
    }
}
