<?php

namespace app\modules\module_page_skins\system;

use app\modules\module_page_skins\system\FunctionCore;

class Weapons extends FunctionCore
{
    protected $Db, $General, $Translate, $languages, $skins, $stickers, $keychains;

    public function __construct($Db, $General, $Translate)
    {
        $this->Db        = $Db;
        $this->General   = $General;
        $this->Translate = $Translate;
        $this->languages = strtolower($_SESSION['language']);

        $keychains_file = realpath($_SERVER['DOCUMENT_ROOT'] . "/app/modules/module_page_skins/cache/keychains-{$this->languages}.json");
        $stickers_file = realpath($_SERVER['DOCUMENT_ROOT'] . "/app/modules/module_page_skins/cache/stickers-{$this->languages}.json");
        $file_exists   = realpath($_SERVER['DOCUMENT_ROOT'] . "/app/modules/module_page_skins/cache/skins-{$this->languages}.json");
        $keychainsFile  = MODULES . "module_page_skins/cache/keychains-{$this->languages}.json";
        $stickersFile  = MODULES . "module_page_skins/cache/stickers-{$this->languages}.json";
        $skinsFile     = MODULES . "module_page_skins/cache/skins-{$this->languages}.json";

        $this->skins    = file_exists($file_exists) ? $skinsFile : MODULES . "module_page_skins/cache/skins-ru.json";
        $this->stickers = file_exists($stickers_file) ? $stickersFile : MODULES . "module_page_skins/cache/stickers-ru.json";
        $this->keychains = file_exists($keychains_file) ? $keychainsFile : MODULES . "module_page_skins/cache/keychains-ru.json";
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

    public function IntefaceWP($id = "All", $server = 0, $team = 0, $skin = null)
    {
        $settingsType = $this->Settings('type');

        if (
            !is_numeric($server) || intval($server) != $server || $server < 0 ||
            (($this->Settings('type') == 1 || $this->Settings('type') == 3) && $server >= $this->Db->table_count['Skins'])
        ) {
            return ['html' => $this->Translate('_nonono')];
        }

        if (!is_numeric($team) || intval($team) != $team || $team > 2 || $team < 0) {
            return ['html' => $this->Translate('_nonono')];
        }

        if (!isset($_SESSION['steamid64'])) {
            return ['html' => $this->Translate('_no_auth')];
        }

        $settingsTeam = $this->Settings('team');
        $player_id = $this->DataDBUser();
        $Skins = $this->get_cache($this->skins);

        $activeSkins = $this->getAllUserSkins($settingsType, $server, $team, $player_id);

        $selectedItems = $this->getUserSelectedItems($settingsType, $server, $team, $player_id);

        $filteredSkins = $this->filterSkinsList($Skins, $id, $team, $skin, $settingsTeam);

        if (empty($filteredSkins)) {
            return ['html' => $this->Translate('_skins_no')];
        }

        $htmlOutput = $this->generateSkinsHtml($filteredSkins, $activeSkins, $selectedItems, $settingsType);

        return ['html' => $htmlOutput];
    }

    private function getAllUserSkins($settingsType, $server, $team, $player_id)
    {
        $activeSkins = [];

        if ($settingsType == 1) {
            $teamwp = empty($team) ? 2 : 3;

            $result = $this->Db->queryAll('Skins', $server, 0, "SELECT `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_nametag`, `weapon_stattrak`, `weapon_sticker_0`, `weapon_sticker_1`, `weapon_sticker_2`, `weapon_sticker_3`, `weapon_keychain` FROM `wp_player_skins` WHERE `steamid` = :steamid AND `weapon_team` = :weapon_team", [
                'steamid' => $player_id,
                'weapon_team' => $teamwp,
            ]);

            foreach ($result as $row) {
                $activeSkins[$row['weapon_defindex']] = [
                    'skin' => $row['weapon_paint_id'],
                    'range' => $row['weapon_wear'],
                    'tag' => $row['weapon_nametag'],
                    'stattrack' => $row['weapon_stattrak'],
                    'stickers' => array_map(function ($sticker) {
                        return explode(';', $sticker)[0];
                    }, [
                        $row['weapon_sticker_0'],
                        $row['weapon_sticker_1'],
                        $row['weapon_sticker_2'],
                        $row['weapon_sticker_3']
                    ]),
                    'keychain' => explode(';', $row['weapon_keychain'])
                ];
            }
        } elseif ($settingsType == 2) {
            $result = $this->Db->queryAll('Skins', 0, 0, "SELECT `weapon_index`, `skin`, `stickers`, `stattrack`, `tag`, `keychain` FROM `sc_skins` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team", [
                'player_id' => $player_id,
                'server_id' => $server,
                'team' => $team,
            ]);

            foreach ($result as $row) {
                $skinParts = explode(';', $row['skin']);
                $activeSkins[$row['weapon_index']] = [
                    'skin' => $skinParts[0],
                    'range' => $skinParts[2] ?? 0,
                    'tag' => $row['tag'],
                    'stattrack' => $row['stattrack'],
                    'stickers' => explode(';', $row['stickers']),
                    'keychain' => explode(';', $row['keychain'])
                ];
            }
        } elseif ($settingsType == 3) {
            $teamwp = empty($team) ? 2 : 3;

            $result = $this->Db->queryAll('Skins', $server, 0, "SELECT `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_nametag`, `weapon_stattrak`, `weapon_sticker_0`, `weapon_sticker_1`, `weapon_sticker_2`, `weapon_sticker_3`, `weapon_keychain` FROM `wp_skins` WHERE `steamid` = :steamid AND `weapon_team` = :weapon_team", [
                'steamid' => $player_id,
                'weapon_team' => $teamwp,
            ]);

            foreach ($result as $row) {
                $activeSkins[$row['weapon_defindex']] = [
                    'skin' => $row['weapon_paint_id'],
                    'range' => $row['weapon_wear'],
                    'tag' => $row['weapon_nametag'],
                    'stattrack' => $row['weapon_stattrak'],
                    'stickers' => array_map(function ($sticker) {
                        return explode(';', $sticker)[0];
                    }, [
                        $row['weapon_sticker_0'],
                        $row['weapon_sticker_1'],
                        $row['weapon_sticker_2'],
                        $row['weapon_sticker_3']
                    ]),
                    'keychain' => explode(';', $row['weapon_keychain'])
                ];
            }
        }

        return $activeSkins;
    }

    private function getUserSelectedItems($settingsType, $server, $team, $player_id)
    {
        if ($settingsType == 1) {
            $teamwp = empty($team) ? 2 : 3;
            return $this->Db->query('Skins', $server, 0, "SELECT (SELECT `knife` FROM `wp_player_knife` WHERE `steamid` = :player_id AND `weapon_team` = :weapon_team LIMIT 1) AS `knife`, (SELECT `weapon_defindex` FROM `wp_player_gloves` WHERE `steamid` = :player_id AND `weapon_team` = :weapon_team LIMIT 1) AS `glove`", [
                'player_id' => $player_id,
                'weapon_team' => $teamwp,
            ]);
        }

        if ($settingsType == 2) {
            return $this->Db->query('Skins', 0, 0, "SELECT `glove`, `knife` FROM `sc_items` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                'player_id' => $player_id,
                'server_id' => $server,
                'team' => $team,
            ]);
        }

        if ($settingsType == 3) {
            $teamwp = empty($team) ? 2 : 3;
            return $this->Db->query('Skins', $server, 0, "SELECT (SELECT `knife` FROM `wp_knife` WHERE `steamid` = :player_id AND `weapon_team` = :weapon_team LIMIT 1) AS `knife`, (SELECT `weapon_defindex` FROM `wp_gloves` WHERE `steamid` = :player_id AND `weapon_team` = :weapon_team LIMIT 1) AS `glove`", [
                'player_id' => $player_id,
                'weapon_team' => $teamwp,
            ]);
        }

        return ['knife' => null, 'glove' => null];
    }

    private function filterSkinsList($Skins, $id, $team, $skin, $settingsTeam)
    {
        if (!empty($skin)) {
            $skinLower = strtolower($skin);
            return array_filter($Skins, function ($wp) use ($skinLower) {
                return strpos(strtolower($wp['name']), $skinLower) !== false;
            });
        }

        return array_filter($Skins, function ($s) use ($id, $team, $settingsTeam) {
            if ($id !== "All" && $s['type'] !== $id) return false;
            if ($settingsTeam == 0 && $s['team'] != $team && $s['team'] != 2) return false;
            return true;
        });
    }

    private function generateSkinsHtml($skins, $activeSkins, $selectedItems, $settingsType)
    {
        $html = '';

        foreach ($skins as $Skin) {
            $skinId = $Skin['id'];
            $activeData = $activeSkins[$skinId] ?? null;

            $html .= $this->generateSingleSkinHtml($Skin, $activeData, $selectedItems, $settingsType);
        }

        return $html;
    }

    private function generateSingleSkinHtml($Skin, $activeData, $selectedItems, $settingsType)
    {
        $id = $Skin['id'];
        $id_name = $Skin['id_name'];
        $name = $Skin['name'];
        $img = $Skin['img'];
        $desc = $this->Translate('_skins_none');

        $color = "";
        $rangeHtml = "";
        $stickerHtml = "";
        $tag = $activeData['tag'] ?? null;
        $stattrack = ($activeData['stattrack'] ?? 0) == 1 ? " / StatTrak™" : "";

        if ($tag) {
            $name = "<s>{$name}</s> {$tag}";
        }

        if ($activeData) {
            $stickerHtml = $this->StickerHtml(
                $activeData['stickers'] ?? [],
                $activeData['keychain'] ?? []
            );
        }

        if (!empty($activeData['skin'])) {
            $skinInfo = $Skin['skins'][$activeData['skin']] ?? null;
            if ($skinInfo) {
                $color = " " . ($skinInfo['id_rarity'] ?? '');
                $img = $skinInfo['image'] ?? $img;
                $descParts = explode('|', $skinInfo['name']);
                $desc = trim($descParts[1] ?? $desc);
            }

            $float = $activeData['range'] ?? 0;
            $floatName = $this->getFloatWearName($float);
            $rangeHtml = "<div class='info-float-range'>{$floatName} / {$float}{$stattrack}<input name='float-range' class='range-1' type='range' min='0.0001' max='0.9999' step='0.0001' value='{$float}' disabled=''></div>";
        } elseif (!empty($stattrack)) {
            $rangeHtml = "<div class='info-float-range'>StatTrak™</div>";
        }

        $isSelected = $this->checkSkinSelection($Skin, $selectedItems, $id_name, $id, $settingsType);
        $choiceClass = $isSelected ? ' choice_active' : '';

        $button = $this->generateSkinButton($Skin, $id_name, $isSelected);

        $addAttributes = $this->getSkinAttributes($Skin, $id_name, $id);

        $disabled_class = '';
        if ($settingsType == 3) {
            $module_status = $this->ModuleStatus();
            if (isset($module_status['wp_skins']) && $module_status['wp_skins'] == 0) {
                $disabled_class = ' disabled_button';
            } elseif ($Skin['type'] == 'Knife' && isset($module_status['wp_knife']) && $module_status['wp_knife'] == 0) {
                $disabled_class = ' disabled_button';
            } elseif ($Skin['type'] == 'Gloves' && isset($module_status['wp_gloves']) && $module_status['wp_gloves'] == 0) {
                $disabled_class = ' disabled_button';
            }
        }

        return <<<HTML
            <div class="block-skin-fon{$choiceClass}{$color}{$disabled_class}" {$addAttributes}>
                {$rangeHtml}{$stickerHtml}{$button}
                <div class="block-skin-img">
                    <img class="loader_img_weapon" src="{$img}" loading="lazy" alt="{$name}">
                </div>
                <div class="block-skin-info">
                    <b>{$name}</b>
                    <span>{$desc}</span>
                </div>
            </div>
        HTML;
    }

    private function getFloatWearName($float)
    {
        if ($float >= 0.45) return 'BS';
        if ($float >= 0.38) return 'WW';
        if ($float >= 0.15) return 'FT';
        if ($float >= 0.07) return 'MW';
        return 'FN';
    }

    private function checkSkinSelection($Skin, $selectedItems, $id_name, $id, $settingsType)
    {
        if ($settingsType == 1 || $settingsType == 3) {
            return ($Skin['type'] == "Knife" && $selectedItems['knife'] == $id_name) ||
                ($Skin['type'] == "Gloves" && $selectedItems['glove'] == $id);
        } else {
            return ($Skin['type'] == "Knife" && $selectedItems['knife'] == $id) ||
                ($Skin['type'] == "Gloves" && $selectedItems['glove'] == $id);
        }
    }

    private function generateSkinButton($Skin, $id_name, $isSelected)
    {
        if ($id_name == 'weapon_knife' || $id_name == 'glove_ct' || $id_name == 'glove_t') {
            $text = $isSelected ? $this->Translate('_choose_default') : $this->Translate('_choose_weapon');
            return '<b class="button-skin">' . $text . '</b>';
        }

        $buttonText = $this->Translate('_skins_check');
        if ($Skin['type'] == "Knife" || $Skin['type'] == "Gloves") {
            return '<b class="button-skin" id="set-skin" id_name="' . $id_name . '">' . $buttonText . '</b>';
        }
        return '<b class="button-skin">' . $buttonText . '</b>';
    }

    private function getSkinAttributes($Skin, $id_name, $id)
    {
        if ($Skin['type'] == "Knife" && $id_name != 'weapon_taser') {
            return "id='SkinChangerAddKnife' id_knife='{$id}'";
        }
        if ($Skin['type'] == "Gloves") {
            return "id='SkinChangerAddGlove' id_glove='{$id}'";
        }
        return "id='set-skin' id_name='{$id_name}'";
    }

    public function ModalWP($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['html' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['html' => $this->Translate('_nonono')];
        if (!isset($_SESSION['steamid64'])) {
            return ['html' => $this->Translate('_no_auth')];
        }

        $ModalSkins = "";

        $skinsData = $this->get_cache($this->skins);

        $selected_skins = array_filter($skinsData, function ($skin) use ($POST) {
            return $skin['id_name'] == $POST['id_name'];
        });

        $skinsToIterate = reset($selected_skins);
        $skinsToIterateAll = $skinsToIterate['skins'];

        $rarityOrder = [
            'rarity_common_weapon' => 1,
            'rarity_uncommon_weapon' => 2,
            'rarity_rare_weapon' => 3,
            'rarity_mythical_weapon' => 4,
            'rarity_legendary_weapon' => 5,
            'rarity_ancient_weapon' => 6,
            'rarity_contraband_weapon' => 7,
        ];

        usort($skinsToIterateAll, function ($a, $b) use ($rarityOrder) {
            $aOrder = $rarityOrder[$a['id_rarity']];
            $bOrder = $rarityOrder[$b['id_rarity']];

            return $aOrder - $bOrder;
        });

        $id = $skinsToIterate['id'];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $skin_active = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT `weapon_paint_id` AS skin FROM `wp_player_skins` WHERE `steamid` = :player_id AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'weapon_defindex' => $id,
                'weapon_team' => $teamwp,
            ]);
            $skin = $skin_active['skin'];
        } else if ($this->Settings('type') == 2) {
            $skin_active = $this->Db->query('Skins', 0, 0, "SELECT `skin` FROM `sc_skins` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team AND `weapon_index` = :weapon_index LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $POST['id_server'],
                'weapon_index' => $id,
                'team' => $POST['id_team'],
            ]);
            $skin = explode(';', $skin_active['skin'])[0];
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $skin_active = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT `weapon_paint_id` AS skin FROM `wp_skins` WHERE `steamid` = :player_id AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'weapon_defindex' => $id,
                'weapon_team' => $teamwp,
            ]);
            $skin = $skin_active['skin'];
        }

        foreach ($skinsToIterateAll as $selectedSkin) {

            $id_skin = $selectedSkin['id_skin'];
            $color = " " . $selectedSkin['id_rarity'];
            $img = $selectedSkin['image'];
            $skinName = trim(explode('|', $selectedSkin['name'])[1]);
            $rarity = $selectedSkin['rarity'];
            $choose_weapon = $this->Translate('_choose_weapon');

            switch (true) {
                case ($skin == $id_skin):
                    $choose_weapon = $this->Translate('_choose_default');
                    $choice = ' choice_active';
                    break;
                default:
                    $choice = '';
                    break;
            }

            $ModalSkins .= <<<HTML
                            <div class="block-skin-fon{$choice}{$color}" id="SkinChangerUpdate" id_skin="{$id_skin}">
                                <div class="block-skin-img">
                                    <img class="loader_img_skin" src="{$img}" loading="lazy" alt="{$skinName}">
                                </div>
                                <div class="block-skin-info">
                                    <b class="skin-name">{$skinName}</b>
                                    <span>{$rarity}</span>
                                </div>
                                <b class='button-skin'>{$choose_weapon}</b>
                            </div>  
            HTML;
        }

        $buttons = <<<HTML
                        <button class="modal_deff" id="SkinChangerSettingModal" weapon_index="{$id}">{$this->Translate('_settings')}</button>
                        <button class="modal_deff" id="SkinChangerNoSkin" weapon_index="{$id}">{$this->Translate('_selection_stand')}</button>  
                    HTML;

        return ['html' => "<div class='skin-modal-js scroll'>{$ModalSkins}</div>" ?: $this->Translate('_skins_no'), 'buttons' => $buttons, 'id' => $id];
    }

    public function SkinChangerSettingModal($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['html' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['html' => $this->Translate('_nonono')];
        if (!is_numeric($POST['weapon_index']) || intval($POST['weapon_index']) != $POST['weapon_index'] || $POST['weapon_index'] < 0) return ['html' => $this->Translate('_nonono')];
        if (!isset($_SESSION['steamid64'])) {
            return ['html' => $this->Translate('_no_auth'), 'count_stickers' => 0];
        }

        $ModalSettings = "";
        $count_stickers = 0;

        $skinsData = $this->get_cache($this->skins);

        $selected_skins = array_filter($skinsData, function ($skin) use ($POST) {
            return $skin['id'] == $POST['weapon_index'];
        });

        $skinsToIterate = reset($selected_skins);

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $skin_active = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT * FROM `wp_player_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_defindex' => $POST['weapon_index'],
                'weapon_team' => $teamwp,
            ]);

            $skin = $skin_active['weapon_paint_id'];
            $float = $skin_active['weapon_wear'];
            $pattern =  $skin_active['weapon_seed'];

            $stickers_data = [
                explode(';', $skin_active['weapon_sticker_0'])[0],
                explode(';', $skin_active['weapon_sticker_1'])[0],
                explode(';', $skin_active['weapon_sticker_2'])[0],
                explode(';', $skin_active['weapon_sticker_3'])[0]
            ];
            $keychain_data = explode(';', $skin_active['weapon_keychain']);

            $skin_stattrack = $skin_active['weapon_stattrak'];
            $skin_stattrack_count = $skin_active['weapon_stattrak_count'];
            $skin_tag = $skin_active['weapon_nametag'];
        } else if ($this->Settings('type') == 2) {

            $skin_active = $this->Db->query('Skins', 0, 0, "SELECT * FROM `sc_skins` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :id_team AND `weapon_index` = :weapon_index LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $POST['id_server'],
                'id_team' => $POST['id_team'],
                'weapon_index' => $POST['weapon_index'],
            ]);
            $explode = explode(';', $skin_active['skin']);
            $skin = $explode[0];
            $float = $explode[2];
            $pattern = $explode[1];

            $stickers_data = explode(';', $skin_active['stickers']);
            $keychain_data = explode(';', $skin_active['keychain']);

            $skin_stattrack = $skin_active['stattrack'];
            $skin_stattrack_count = $skin_active['stattrack_count'];
            $skin_tag = $skin_active['tag'];
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $skin_active = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT * FROM `wp_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_defindex' => $POST['weapon_index'],
                'weapon_team' => $teamwp,
            ]);

            $skin = $skin_active['weapon_paint_id'];
            $float = $skin_active['weapon_wear'];
            $pattern =  $skin_active['weapon_seed'];

            $stickers_data = [
                explode(';', $skin_active['weapon_sticker_0'])[0],
                explode(';', $skin_active['weapon_sticker_1'])[0],
                explode(';', $skin_active['weapon_sticker_2'])[0],
                explode(';', $skin_active['weapon_sticker_3'])[0]
            ];
            $keychain_data = explode(';', $skin_active['weapon_keychain']);

            $skin_stattrack = $skin_active['weapon_stattrak'];
            $skin_stattrack_count = $skin_active['weapon_stattrak_count'];
            $skin_tag = $skin_active['weapon_nametag'];
        }

        if ($skinsToIterate['type'] == "Knife" || $skinsToIterate['type'] == "Gloves") {
            $class = "sk-modal-settings-type-1";
        } else {
            $class = "sk-modal-settings-type-2";
            $Sticker_html = $this->Sticker($stickers_data, $keychain_data);
            $count_stickers = count($this->get_cache($this->stickers));
            $Sticker_table = <<<HTML
                                <div class="sk-modal-block">
                                    <div class="search-skin-input">
                                        <input type="text" id="search_js_stickers" placeholder="{$this->Translate('_search')}">
                                    </div>    
                                    <div class="stickers-modal-js scroll">{$this->SkinChangerStickers(null,$this->get_cache($this->stickers))['html']}</div>
                                    <div id="stickers-pagination"></div> 
                                </div>
                            HTML;
        }

        if ($skinsToIterate['type'] != "Gloves") {
            if ($skin_stattrack == 1) {
                $stattrack = " on";
                $stattrack_text = "StatTrak ON";
            } else {
                $stattrack = " off";
                $stattrack_text = "StatTrak OFF";
            }
            $StatTrak = <<<HTML
                            <div class="sk-modal-setting-2">
                                <div class="sk-modal-setting-form">
                                    <div class="sk-modal_name">StatTrak</div>
                                    <input name="stattrack_count" class="sk-modal-input" placeholder="0" min="0" max="999999" type="number" value="{$skin_stattrack_count}">
                                </div>
                                <div class="sk-modal-setting-form">
                                    <a class="button sk-modal-StatTrak-button{$stattrack}">{$stattrack_text}</a>
                                </div>
                            </div>
                        HTML;
        }

        $NameTag = <<<HTML
                        <div class="sk-modal-setting-1">
                            <div class="sk-modal-setting-form">
                                <div class="sk-modal_name">NameTag</div>
                                <input name="nametag" class="sk-modal-input" placeholder="{$this->Translate('_name')}" type="text" value="{$skin_tag}">
                            </div>
                        </div>
                    HTML;

        $img = $skinsToIterate['img'];
        if (!empty($skin)) {
            $img = $skinsToIterate['skins'][$skin]['image'] ?: $img;
        }

        $ModalSettings = <<<HTML
                            <div class="{$class}">
                                <div class="sk-modal-block">
                                    <div class="sk-modal-weapons">
                                        <img class="sk-modal-weapon-img" src="{$img}" alt="weapon">
                                        {$Sticker_html}
                                    </div>
                                    <form id="SkinChangerSettingForm">
                                        {$StatTrak}
                                        <div class="sk-modal-setting-2">
                                            <div class="sk-modal-setting-form">
                                                <div class="sk-modal_name">Float</div>
                                                <input name="float" class="sk-modal-input" placeholder="0" min="0" max="0.9999" step="0.0001" type="number" value="{$float}">
                                            </div>
                                            <div class="sk-modal-setting-form">
                                                <div class="sk-modal-setting-buttons">
                                                    <div class="sk-modal-setting-button" data-value="0.0001">FN</div>
                                                    <div class="sk-modal-setting-button" data-value="0.07">MW</div>
                                                    <div class="sk-modal-setting-button" data-value="0.15">FT</div>
                                                    <div class="sk-modal-setting-button" data-value="0.38">WW</div>
                                                    <div class="sk-modal-setting-button" data-value="0.45">BS</div>
                                                </div>
                                                <input name="float-range" class="range-1" type="range" min="0.0001" max="0.9999" step="0.0001" value="{$float}">
                                            </div>
                                        </div>
                                        <div class="sk-modal-setting-2">
                                            <div class="sk-modal-setting-form">
                                                <div class="sk-modal_name">{$this->Translate('_pattern')}</div>
                                                <input name="pattern" class="sk-modal-input" placeholder="1" min="0" max="999" step="0" type="number" value="{$pattern}">
                                            </div>
                                            <div class="sk-modal-setting-form">           
                                                <input name="pattern-range" class="range-2" type="range" min="0" max="999" step="0" value="{$pattern}">
                                            </div>
                                        </div>
                                        {$NameTag}   
                                    </form>
                                    <button class="sk-modal-button width-100" id="SkinChangerSetting">{$this->Translate('_stats_save_settings')}</button>
                                </div>
                                {$Sticker_table}
                            </div>
                    HTML;

        return ['html' => (!empty($ModalSettings) ? $ModalSettings : $this->Translate('_error')), 'count_stickers' => $count_stickers];
    }

    public function StickersKeychainHtml($POST)
    {
        if (!isset($_SESSION['steamid64'])) {
            return ['html' => $this->Translate('_no_auth'), 'count' => 0];
        }
        if (!isset($POST['id']) || !is_numeric($POST['id'])) {
            return ['html' => $this->Translate('_nonono'), 'count' => 0];
        }

        $table = '';

        if ($POST['id'] > 4) {
            $data = $this->get_cache($this->keychains);
            $count = count($data);
        } else {
            $data = $this->get_cache($this->stickers);
            $count = count($data);
        }

        $table = <<<HTML
                <div class="search-skin-input">
                    <input type="text" id="search_js_stickers" placeholder="{$this->Translate('_search')}">
                </div>    
                <div class="stickers-modal-js scroll">{$this->SkinChangerStickers($POST)['html']}</div>
                <div id="stickers-pagination"></div>
        HTML;

        return ['html' => (!empty($table) ? $table : $this->Translate('_error')), 'count' => $count];
    }

    public function Sticker($stickers_data, $keychain_data)
    {

        $stickers_get_cache = $this->get_cache($this->stickers);
        $keychains_get_cache = $this->get_cache($this->keychains);

        $svg_image = [
            1 => '<svg class="img-slot" viewBox="0 -10 512.00005 512"><path d="m436.960938 75.007812c-85.984376-85.984374-216.566407-97.453124-314.546876-37.441406-3.535156 2.167969-4.648437 6.789063-2.480468 10.324219 2.164062 3.539063 6.789062 4.648437 10.324218 2.480469 37.734376-23.113282 81.195313-35.328125 125.683594-35.328125 132.878906 0 240.984375 108.105469 240.984375 240.980469 0 50.964843-15.589843 99.367187-45.140625 140.433593-24.300781-14.980469-51.148437-24.828125-79.167968-29.070312 1.1875-7.277344.140624-4.433594-14.433594-92.210938-.574219-3.234375.515625-6.664062 2.933594-9.019531l60.910156-59.378906c14.847656-14.457032 6.90625-39.96875-13.960938-43.003906l-84.195312-12.234376c-3.324219-.480468-6.1875-2.5625-7.667969-5.574218l-37.660156-76.285156c-9.238281-18.738282-35.972657-18.726563-45.203125 0l-37.660156 76.285156c-1.480469 3.011718-4.34375 5.09375-7.667969 5.574218l-84.191407 12.234376c-20.855468 3.035156-28.820312 28.539062-13.964843 43.003906l19.027343 18.546875c2.964844 2.894531 7.71875 2.832031 10.613282-.128907 2.890625-2.972656 2.832031-7.730468-.132813-10.621093l-19.027343-18.550781c-6.125-5.976563-2.636719-16.1875 5.644531-17.386719l84.183593-12.230469c8.21875-1.191406 15.316407-6.359375 18.992188-13.796875l37.644531-76.285156c3.734375-7.59375 14.546875-7.59375 18.28125 0l37.644531 76.285156c3.675782 7.4375 10.773438 12.605469 18.992188 13.796875l84.183594 12.230469c8.265625 1.195312 11.777344 11.402343 5.644531 17.386719l-60.910156 59.382812c-4.84375 4.714844-7.617188 11.289062-7.617188 18.046875 0 8.324219 15.824219 87.273437 14.644531 92.203125-9.269531-.753906-18.734374-.902344-28.109374-.390625l-61.882813-32.542969c-7.335937-3.851562-16.113281-3.859375-23.460937 0l-75.296876 39.589844c-7.09375 3.707031-14.957031-1.726562-14.957031-8.898438 0-3.949218 14.90625-84.363281 14.90625-89.960937 0-6.757813-2.773437-13.332031-7.605469-18.046875l-20.390624-19.871094c-2.972657-2.894531-7.730469-2.832031-10.621094.128906-2.894532 2.972657-2.832032 7.726563.140625 10.621094l20.390625 19.871094c2.375 2.324219 3.5 5.722656 2.921875 9.027344-14.804688 87.0625-14.753907 84.238281-14.753907 88.230468 0 18.65625 19.929688 31.09375 36.957032 22.191407l75.292968-39.589844c2.925782-1.53125 6.566407-1.53125 9.492188 0l42.421875 22.300781c-21.851563 3.925782-43.042969 11.253906-62.59375 21.753906-3.652344 1.960938-5.023437 6.507813-3.0625 10.160157 1.960937 3.652343 6.507813 5.023437 10.160156 3.0625 56.734375-30.441407 127.332031-31.886719 186.925781.507812-66.542968 17.527344-196.628906 51.796875-261.308593 68.890625 13.191406-20.832031 30.238281-39.25 49.988281-53.902344 3.324219-2.472656 4.023438-7.179687 1.550781-10.5-2.472656-3.335937-7.175781-4.027343-10.5-1.5625-24.347656 18.070313-44.871093 41.472657-59.699219 67.984376-162.230468-75.238282-189.214843-295.917969-48.164062-407.675782 3.253906-2.574218 3.800781-7.296875 1.226562-10.546875-2.578124-3.253906-7.300781-3.800781-10.546874-1.222656-151.703126 120.195313-120.484376 359.375 57.808593 436.035156 3.984375 1.726563-14.550781 5.519531 300.554688-77.480469 1.628906-.398437 3.300781-1.578124 4.191406-2.738281 77.03125-100.992187 68.730469-244.664062-22.679687-336.074219zm0 0"></path></svg>',
            2 => '<svg class="img-slot" viewBox="0 -10 512.00005 512"><path d="m436.960938 75.007812c-85.984376-85.984374-216.566407-97.453124-314.546876-37.441406-3.535156 2.167969-4.648437 6.789063-2.480468 10.324219 2.164062 3.539063 6.789062 4.648437 10.324218 2.480469 37.734376-23.113282 81.195313-35.328125 125.683594-35.328125 132.878906 0 240.984375 108.105469 240.984375 240.980469 0 50.964843-15.589843 99.367187-45.140625 140.433593-24.300781-14.980469-51.148437-24.828125-79.167968-29.070312 1.1875-7.277344.140624-4.433594-14.433594-92.210938-.574219-3.234375.515625-6.664062 2.933594-9.019531l60.910156-59.378906c14.847656-14.457032 6.90625-39.96875-13.960938-43.003906l-84.195312-12.234376c-3.324219-.480468-6.1875-2.5625-7.667969-5.574218l-37.660156-76.285156c-9.238281-18.738282-35.972657-18.726563-45.203125 0l-37.660156 76.285156c-1.480469 3.011718-4.34375 5.09375-7.667969 5.574218l-84.191407 12.234376c-20.855468 3.035156-28.820312 28.539062-13.964843 43.003906l19.027343 18.546875c2.964844 2.894531 7.71875 2.832031 10.613282-.128907 2.890625-2.972656 2.832031-7.730468-.132813-10.621093l-19.027343-18.550781c-6.125-5.976563-2.636719-16.1875 5.644531-17.386719l84.183593-12.230469c8.21875-1.191406 15.316407-6.359375 18.992188-13.796875l37.644531-76.285156c3.734375-7.59375 14.546875-7.59375 18.28125 0l37.644531 76.285156c3.675782 7.4375 10.773438 12.605469 18.992188 13.796875l84.183594 12.230469c8.265625 1.195312 11.777344 11.402343 5.644531 17.386719l-60.910156 59.382812c-4.84375 4.714844-7.617188 11.289062-7.617188 18.046875 0 8.324219 15.824219 87.273437 14.644531 92.203125-9.269531-.753906-18.734374-.902344-28.109374-.390625l-61.882813-32.542969c-7.335937-3.851562-16.113281-3.859375-23.460937 0l-75.296876 39.589844c-7.09375 3.707031-14.957031-1.726562-14.957031-8.898438 0-3.949218 14.90625-84.363281 14.90625-89.960937 0-6.757813-2.773437-13.332031-7.605469-18.046875l-20.390624-19.871094c-2.972657-2.894531-7.730469-2.832031-10.621094.128906-2.894532 2.972657-2.832032 7.726563.140625 10.621094l20.390625 19.871094c2.375 2.324219 3.5 5.722656 2.921875 9.027344-14.804688 87.0625-14.753907 84.238281-14.753907 88.230468 0 18.65625 19.929688 31.09375 36.957032 22.191407l75.292968-39.589844c2.925782-1.53125 6.566407-1.53125 9.492188 0l42.421875 22.300781c-21.851563 3.925782-43.042969 11.253906-62.59375 21.753906-3.652344 1.960938-5.023437 6.507813-3.0625 10.160157 1.960937 3.652343 6.507813 5.023437 10.160156 3.0625 56.734375-30.441407 127.332031-31.886719 186.925781.507812-66.542968 17.527344-196.628906 51.796875-261.308593 68.890625 13.191406-20.832031 30.238281-39.25 49.988281-53.902344 3.324219-2.472656 4.023438-7.179687 1.550781-10.5-2.472656-3.335937-7.175781-4.027343-10.5-1.5625-24.347656 18.070313-44.871093 41.472657-59.699219 67.984376-162.230468-75.238282-189.214843-295.917969-48.164062-407.675782 3.253906-2.574218 3.800781-7.296875 1.226562-10.546875-2.578124-3.253906-7.300781-3.800781-10.546874-1.222656-151.703126 120.195313-120.484376 359.375 57.808593 436.035156 3.984375 1.726563-14.550781 5.519531 300.554688-77.480469 1.628906-.398437 3.300781-1.578124 4.191406-2.738281 77.03125-100.992187 68.730469-244.664062-22.679687-336.074219zm0 0"></path></svg>',
            3 => '<svg class="img-slot" viewBox="0 -10 512.00005 512"><path d="m436.960938 75.007812c-85.984376-85.984374-216.566407-97.453124-314.546876-37.441406-3.535156 2.167969-4.648437 6.789063-2.480468 10.324219 2.164062 3.539063 6.789062 4.648437 10.324218 2.480469 37.734376-23.113282 81.195313-35.328125 125.683594-35.328125 132.878906 0 240.984375 108.105469 240.984375 240.980469 0 50.964843-15.589843 99.367187-45.140625 140.433593-24.300781-14.980469-51.148437-24.828125-79.167968-29.070312 1.1875-7.277344.140624-4.433594-14.433594-92.210938-.574219-3.234375.515625-6.664062 2.933594-9.019531l60.910156-59.378906c14.847656-14.457032 6.90625-39.96875-13.960938-43.003906l-84.195312-12.234376c-3.324219-.480468-6.1875-2.5625-7.667969-5.574218l-37.660156-76.285156c-9.238281-18.738282-35.972657-18.726563-45.203125 0l-37.660156 76.285156c-1.480469 3.011718-4.34375 5.09375-7.667969 5.574218l-84.191407 12.234376c-20.855468 3.035156-28.820312 28.539062-13.964843 43.003906l19.027343 18.546875c2.964844 2.894531 7.71875 2.832031 10.613282-.128907 2.890625-2.972656 2.832031-7.730468-.132813-10.621093l-19.027343-18.550781c-6.125-5.976563-2.636719-16.1875 5.644531-17.386719l84.183593-12.230469c8.21875-1.191406 15.316407-6.359375 18.992188-13.796875l37.644531-76.285156c3.734375-7.59375 14.546875-7.59375 18.28125 0l37.644531 76.285156c3.675782 7.4375 10.773438 12.605469 18.992188 13.796875l84.183594 12.230469c8.265625 1.195312 11.777344 11.402343 5.644531 17.386719l-60.910156 59.382812c-4.84375 4.714844-7.617188 11.289062-7.617188 18.046875 0 8.324219 15.824219 87.273437 14.644531 92.203125-9.269531-.753906-18.734374-.902344-28.109374-.390625l-61.882813-32.542969c-7.335937-3.851562-16.113281-3.859375-23.460937 0l-75.296876 39.589844c-7.09375 3.707031-14.957031-1.726562-14.957031-8.898438 0-3.949218 14.90625-84.363281 14.90625-89.960937 0-6.757813-2.773437-13.332031-7.605469-18.046875l-20.390624-19.871094c-2.972657-2.894531-7.730469-2.832031-10.621094.128906-2.894532 2.972657-2.832032 7.726563.140625 10.621094l20.390625 19.871094c2.375 2.324219 3.5 5.722656 2.921875 9.027344-14.804688 87.0625-14.753907 84.238281-14.753907 88.230468 0 18.65625 19.929688 31.09375 36.957032 22.191407l75.292968-39.589844c2.925782-1.53125 6.566407-1.53125 9.492188 0l42.421875 22.300781c-21.851563 3.925782-43.042969 11.253906-62.59375 21.753906-3.652344 1.960938-5.023437 6.507813-3.0625 10.160157 1.960937 3.652343 6.507813 5.023437 10.160156 3.0625 56.734375-30.441407 127.332031-31.886719 186.925781.507812-66.542968 17.527344-196.628906 51.796875-261.308593 68.890625 13.191406-20.832031 30.238281-39.25 49.988281-53.902344 3.324219-2.472656 4.023438-7.179687 1.550781-10.5-2.472656-3.335937-7.175781-4.027343-10.5-1.5625-24.347656 18.070313-44.871093 41.472657-59.699219 67.984376-162.230468-75.238282-189.214843-295.917969-48.164062-407.675782 3.253906-2.574218 3.800781-7.296875 1.226562-10.546875-2.578124-3.253906-7.300781-3.800781-10.546874-1.222656-151.703126 120.195313-120.484376 359.375 57.808593 436.035156 3.984375 1.726563-14.550781 5.519531 300.554688-77.480469 1.628906-.398437 3.300781-1.578124 4.191406-2.738281 77.03125-100.992187 68.730469-244.664062-22.679687-336.074219zm0 0"></path></svg>',
            4 => '<svg class="img-slot" viewBox="0 -10 512.00005 512"><path d="m436.960938 75.007812c-85.984376-85.984374-216.566407-97.453124-314.546876-37.441406-3.535156 2.167969-4.648437 6.789063-2.480468 10.324219 2.164062 3.539063 6.789062 4.648437 10.324218 2.480469 37.734376-23.113282 81.195313-35.328125 125.683594-35.328125 132.878906 0 240.984375 108.105469 240.984375 240.980469 0 50.964843-15.589843 99.367187-45.140625 140.433593-24.300781-14.980469-51.148437-24.828125-79.167968-29.070312 1.1875-7.277344.140624-4.433594-14.433594-92.210938-.574219-3.234375.515625-6.664062 2.933594-9.019531l60.910156-59.378906c14.847656-14.457032 6.90625-39.96875-13.960938-43.003906l-84.195312-12.234376c-3.324219-.480468-6.1875-2.5625-7.667969-5.574218l-37.660156-76.285156c-9.238281-18.738282-35.972657-18.726563-45.203125 0l-37.660156 76.285156c-1.480469 3.011718-4.34375 5.09375-7.667969 5.574218l-84.191407 12.234376c-20.855468 3.035156-28.820312 28.539062-13.964843 43.003906l19.027343 18.546875c2.964844 2.894531 7.71875 2.832031 10.613282-.128907 2.890625-2.972656 2.832031-7.730468-.132813-10.621093l-19.027343-18.550781c-6.125-5.976563-2.636719-16.1875 5.644531-17.386719l84.183593-12.230469c8.21875-1.191406 15.316407-6.359375 18.992188-13.796875l37.644531-76.285156c3.734375-7.59375 14.546875-7.59375 18.28125 0l37.644531 76.285156c3.675782 7.4375 10.773438 12.605469 18.992188 13.796875l84.183594 12.230469c8.265625 1.195312 11.777344 11.402343 5.644531 17.386719l-60.910156 59.382812c-4.84375 4.714844-7.617188 11.289062-7.617188 18.046875 0 8.324219 15.824219 87.273437 14.644531 92.203125-9.269531-.753906-18.734374-.902344-28.109374-.390625l-61.882813-32.542969c-7.335937-3.851562-16.113281-3.859375-23.460937 0l-75.296876 39.589844c-7.09375 3.707031-14.957031-1.726562-14.957031-8.898438 0-3.949218 14.90625-84.363281 14.90625-89.960937 0-6.757813-2.773437-13.332031-7.605469-18.046875l-20.390624-19.871094c-2.972657-2.894531-7.730469-2.832031-10.621094.128906-2.894532 2.972657-2.832032 7.726563.140625 10.621094l20.390625 19.871094c2.375 2.324219 3.5 5.722656 2.921875 9.027344-14.804688 87.0625-14.753907 84.238281-14.753907 88.230468 0 18.65625 19.929688 31.09375 36.957032 22.191407l75.292968-39.589844c2.925782-1.53125 6.566407-1.53125 9.492188 0l42.421875 22.300781c-21.851563 3.925782-43.042969 11.253906-62.59375 21.753906-3.652344 1.960938-5.023437 6.507813-3.0625 10.160157 1.960937 3.652343 6.507813 5.023437 10.160156 3.0625 56.734375-30.441407 127.332031-31.886719 186.925781.507812-66.542968 17.527344-196.628906 51.796875-261.308593 68.890625 13.191406-20.832031 30.238281-39.25 49.988281-53.902344 3.324219-2.472656 4.023438-7.179687 1.550781-10.5-2.472656-3.335937-7.175781-4.027343-10.5-1.5625-24.347656 18.070313-44.871093 41.472657-59.699219 67.984376-162.230468-75.238282-189.214843-295.917969-48.164062-407.675782 3.253906-2.574218 3.800781-7.296875 1.226562-10.546875-2.578124-3.253906-7.300781-3.800781-10.546874-1.222656-151.703126 120.195313-120.484376 359.375 57.808593 436.035156 3.984375 1.726563-14.550781 5.519531 300.554688-77.480469 1.628906-.398437 3.300781-1.578124 4.191406-2.738281 77.03125-100.992187 68.730469-244.664062-22.679687-336.074219zm0 0"></path></svg>',
            5 => '<svg class="img-slot" id="Capa_1" x="0px" y="0px" viewBox="0 0 512.001 512.001" xml:space="preserve"><g><g><path d="M321.645,34.956c-3.883-3.927-10.213-3.964-14.142-0.081l-0.297,0.296c-3.905,3.905-3.905,10.237,0,14.143 c1.952,1.953,4.511,2.929,7.071,2.929c2.559,0,5.119-0.976,7.071-2.929l0.216-0.216C325.491,45.215,325.527,38.884,321.645,34.956 z"></path></g></g><g><g><path d="M476.873,35.172c-36.359-36.358-91.758-45.528-137.857-22.818c-4.954,2.441-6.992,8.436-4.551,13.39 c2.441,4.954,8.436,6.989,13.39,4.551c38.411-18.923,84.577-11.28,114.877,19.021c38.979,38.979,38.979,102.404,0,141.383 c-36.592,36.593-94.728,38.831-133.959,6.719l14.241-14.241c14.371,11.169,31.692,16.776,49.027,16.776 c20.482,0,40.963-7.796,56.557-23.389c31.185-31.185,31.185-81.927,0-113.113c-31.186-31.185-81.927-31.185-113.113,0 c-28.789,28.789-30.978,74.233-6.613,105.584l-14.231,14.231c-25.086-30.596-29.936-73.166-11.563-108.933 c2.523-4.913,0.587-10.94-4.326-13.464c-4.913-2.523-10.94-0.587-13.464,4.326c-19.335,37.638-17.252,81.556,3.846,116.557 c-5.816,1.432-11.325,4.413-15.86,8.948l-10.131,10.131l-45.514-45.514c-8.686-8.686-20.234-13.469-32.517-13.469 s-23.831,4.784-32.517,13.469L13.492,294.418c-17.929,17.93-17.929,47.103,0,65.034l139.103,139.103 c8.965,8.965,20.741,13.447,32.516,13.447s23.552-4.482,32.517-13.447l38.292-38.293c3.906-3.905,3.906-10.237,0-14.143 c-3.904-3.905-10.237-3.905-14.142,0l-38.292,38.293c-10.132,10.132-26.617,10.133-36.749,0L27.634,345.31 c-10.132-10.132-10.132-26.618,0-36.749l139.103-139.104c4.908-4.908,11.434-7.611,18.375-7.611s13.467,2.703,18.375,7.611 l139.103,139.103c4.908,4.908,7.611,11.434,7.611,18.375s-2.703,13.467-7.611,18.375l-50.145,50.144 c-3.906,3.905-3.906,10.237,0,14.143c3.906,3.905,10.238,3.905,14.142,0l50.145-50.144c17.93-17.93,17.93-47.104,0-65.034 l-45.514-45.514l10.131-10.131c4.543-4.543,7.528-10.063,8.956-15.89c18.926,11.355,40.329,17.039,61.736,17.039 c30.722,0,61.445-11.694,84.833-35.082C523.65,158.063,523.65,81.949,476.873,35.172z M349.626,77.592 c11.329-11.33,26.392-17.569,42.414-17.569s31.085,6.24,42.414,17.569c11.33,11.329,17.569,26.392,17.569,42.414 s-6.24,31.085-17.569,42.414c-11.329,11.33-26.392,17.569-42.414,17.569s-31.085-6.24-42.414-17.569 c-11.33-11.329-17.569-26.392-17.569-42.414C332.057,103.985,338.296,88.921,349.626,77.592z M307.206,224.63l-10.131,10.131 l-19.791-19.791l10.131-10.131c5.455-5.457,14.334-5.458,19.791,0C312.663,210.297,312.663,219.174,307.206,224.63z"></path></g></g><g><g><path d="M242.226,269.819c-31.493-31.491-82.735-31.492-114.229,0c-31.492,31.493-31.492,82.735,0,114.229 c15.747,15.747,36.43,23.62,57.114,23.62c20.685,0,41.368-7.873,57.115-23.62C273.718,352.555,273.718,301.313,242.226,269.819z M228.083,369.906c-11.479,11.479-26.739,17.8-42.972,17.8s-31.494-6.321-42.972-17.8c-11.479-11.479-17.8-26.739-17.8-42.972 c0-16.233,6.321-31.493,17.8-42.972c11.848-11.848,27.409-17.772,42.972-17.772s31.124,5.924,42.972,17.772 C251.779,307.656,251.779,346.212,228.083,369.906z"></path></g></g><g><g><path d="M281.916,420.124c-1.859-1.87-4.439-2.93-7.07-2.93c-2.63,0-5.21,1.06-7.07,2.93c-1.86,1.86-2.93,4.43-2.93,7.07 c0,2.63,1.07,5.21,2.93,7.07s4.44,2.93,7.07,2.93s5.21-1.07,7.07-2.93c1.87-1.86,2.93-4.44,2.93-7.07 C284.846,424.554,283.786,421.984,281.916,420.124z"></path></g></g></svg>',
        ];

        $svg = '<svg class="plus-slot" viewBox="0 0 448 512"><path d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32V224H48c-17.7 0-32 14.3-32 32s14.3 32 32 32H192V432c0 17.7 14.3 32 32 32s32-14.3 32-32V288H400c17.7 0 32-14.3 32-32s-14.3-32-32-32H256V80z"></path></svg>';

        $stickersToIterate = '<div class="sk-modal-weapon-stickers">
                            <button class="sk-modal-weapon-sticker" id="4">' .
            (!empty($stickers_data[0]) ? '<img src="' . $stickers_get_cache[$stickers_data[0]]['image'] . '" alt="sticker">' : $svg . $svg_image[4]) .
            '</button>
                            <button class="sk-modal-weapon-sticker" id="3">' .
            (!empty($stickers_data[1]) ? '<img src="' . $stickers_get_cache[$stickers_data[1]]['image'] . '" alt="sticker">' : $svg . $svg_image[3]) .
            '</button>
                            <button class="sk-modal-weapon-sticker" id="2">' .
            (!empty($stickers_data[2]) ? '<img src="' . $stickers_get_cache[$stickers_data[2]]['image'] . '" alt="sticker">' : $svg . $svg_image[2]) .
            '</button>
                            <button class="sk-modal-weapon-sticker" id="1">' .
            (!empty($stickers_data[3]) ? '<img src="' . $stickers_get_cache[$stickers_data[3]]['image'] . '" alt="sticker">' : $svg . $svg_image[1]) .
            '</button>
                            <button class="sk-modal-weapon-sticker" id="5">' .
            (!empty($keychain_data[0]) ? '<img src="' . $keychains_get_cache[$keychain_data[0]]['image'] . '" alt="keychain">' : $svg . $svg_image[5]) .
            '</button> 
                        </div>';
        return $stickersToIterate;
    }

    public function StickerHtml($stickers_data, $keychain_data)
    {

        $stickers_get_cache = $this->get_cache($this->stickers);
        $keychains_get_cache = $this->get_cache($this->keychains);

        $stickersToIterate = '<div class="weapon-stickers">' .
            (!empty($stickers_data[0]) ? '<img src="' . $stickers_get_cache[$stickers_data[0]]['image'] . '" data-tippy-content="' . $stickers_get_cache[$stickers_data[0]]['name'] . '" data-tippy-placement="left" alt="sticker">' : '') .
            (!empty($stickers_data[1]) ? '<img src="' . $stickers_get_cache[$stickers_data[1]]['image'] . '" data-tippy-content="' . $stickers_get_cache[$stickers_data[1]]['name'] . '" data-tippy-placement="left" alt="sticker">' : '') .
            (!empty($stickers_data[2]) ? '<img src="' . $stickers_get_cache[$stickers_data[2]]['image'] . '" data-tippy-content="' . $stickers_get_cache[$stickers_data[2]]['name'] . '" data-tippy-placement="left" alt="sticker">' : '') .
            (!empty($stickers_data[3]) ? '<img src="' . $stickers_get_cache[$stickers_data[3]]['image'] . '" data-tippy-content="' . $stickers_get_cache[$stickers_data[3]]['name'] . '" data-tippy-placement="left" alt="sticker">' : '') .
            (!empty($keychain_data[0]) ? '<img src="' . $keychains_get_cache[$keychain_data[0]]['image'] . '" data-tippy-content="' . $keychains_get_cache[$keychain_data[0]]['name'] . '" data-tippy-placement="left" alt="keychain">' : '') .
            '</div>';
        return $stickersToIterate;
    }

    public function SkinChangerStickers($POST = null, $stickersToIterate = null)
    {
        $ModalStickers = "";

        if ($stickersToIterate !== null) {
            $POST = is_array($POST) ? $POST : [];
        } elseif (!is_array($POST)) {
            return ['html' => $this->Translate('_nonono'), 'count_stickers' => 0];
        } elseif (!isset($_SESSION['steamid64'])) {
            return ['html' => $this->Translate('_no_auth'), 'count_stickers' => 0];
        } elseif (($POST['id'] ?? 0) > 4) {
            $stickersToIterate = $this->get_cache($this->keychains);
        } else {
            $stickersToIterate = $this->get_cache($this->stickers);
        }

        $count_stickers = count($stickersToIterate);

        $currentPage = isset($POST['page_stickers']) ? intval($POST['page_stickers']) : 1;
        $itemsPerPage = 12;
        $startIndex = ($currentPage - 1) * $itemsPerPage;

        if (!empty($POST['text_stickers'])) {
            $searchTerm = strtolower($POST['text_stickers']);
            $stickersToIterate = array_filter($stickersToIterate, function ($st) use ($searchTerm) {
                return strpos(strtolower($st['name']), $searchTerm) !== false;
            });
            $count_stickers = count($stickersToIterate);
        }

        $rarityOrder = [
            'rarity_default' => 1,
            'rarity_uncommon' => 2,
            'rarity_rare' => 3,
            'rarity_mythical' => 4,
            'rarity_legendary' => 5,
            'rarity_ancient' => 6,
            'rarity_contraband' => 7,
        ];

        uasort($stickersToIterate, function ($a, $b) use ($rarityOrder) {
            $aOrder = $rarityOrder[$a['id_rarity']];
            $bOrder = $rarityOrder[$b['id_rarity']];

            return $aOrder - $bOrder;
        });

        function array_slice_assoc($array, $offset, $length = null, $preserve_keys = false)
        {
            $keys = array_slice(array_keys($array), $offset, $length, $preserve_keys);
            $values = array_slice($array, $offset, $length, $preserve_keys);
            return array_combine($keys, $values);
        }

        $limitedStickers = array_slice_assoc($stickersToIterate, $startIndex, $itemsPerPage, true);

        foreach ($limitedStickers as $key => $stickers) {
            $name = trim(explode('|', $stickers['name'])[1]);
            $rarity = ' ' . $stickers['id_rarity'];
            $img = '<img class="loader_img_sticker" src="' . $stickers['image'] . '" loading="lazy" alt="">';
            if ($key == 0) {
                $img = '';
            }
            $ModalStickers .= <<<HTML
                <div class="sticker-modal-fon{$rarity}" id="StickerUpdate" id_sticker="{$key}">
                    <div class="sticker-modal-img">{$img}</div>
                    <div class="stickers-modal-info">
                        <b class="sticker-name">{$name}</b>
                    </div>
                </div>
            HTML;
        }

        return ['html' => !empty($ModalStickers) ? $ModalStickers : $this->Translate('_nono'), 'count_stickers' => $count_stickers];
    }

    public function SkinChangerUpdate($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['weapon_index']) || intval($POST['weapon_index']) != $POST['weapon_index'] || $POST['weapon_index'] < 0) return ['html' => $this->Translate('_nonono')];

        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];
        if ($this->TableSearch()) return ['status' => 'error', 'text' => $this->Translate('_adm_table_no')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];
        if (!isset($POST['id_server'])) return ['status' => 'error', 'text' => $this->Translate('_serv_right')];
        if (!isset($POST['id_team'])) return ['status' => 'error', 'text' => $this->Translate('_command_left')];

        $skinsData = $this->get_cache($this->skins);
        $skinsData = array_filter($skinsData, function ($skin) use ($POST) {
            return $skin['id'] == $POST['weapon_index'];
        });
        $skinsData = reset($skinsData);

        if (empty($skinsData['name'])) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        if (empty($skinsData['skins'][$POST['id_skin']])) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;

            $SkinDb = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT * FROM `wp_player_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :teamwp LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_defindex' => $POST['weapon_index'],
                'teamwp' => $teamwp,
            ]);
            $weapon_wear = !empty($SkinDb['weapon_wear']) ? $SkinDb['weapon_wear'] : 0;
            $weapon_seed = !empty($SkinDb['weapon_seed']) ? $SkinDb['weapon_seed'] : 0;

            if (empty($SkinDb['weapon_defindex'])) {
                $query = "INSERT INTO `wp_player_skins` (`steamid`, `weapon_team`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`) 
                      VALUES(:steamid, :weapon_team, :weapon_defindex, :weapon_paint_id, :weapon_wear, :weapon_seed)";
                $params = [
                    'steamid' => $this->DataDBUser(),
                    'weapon_team' => $teamwp,
                    'weapon_defindex' => $POST['weapon_index'],
                    'weapon_paint_id' => $POST['id_skin'],
                    'weapon_wear' => $weapon_wear,
                    'weapon_seed' => $weapon_seed,
                ];
            } else {
                $query = "UPDATE `wp_player_skins` SET `weapon_paint_id` = :weapon_paint_id WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1";
                $params = [
                    'weapon_paint_id' => $POST['id_skin'],
                    'steamid' => $SkinDb['steamid'],
                    'weapon_defindex' => $SkinDb['weapon_defindex'],
                    'weapon_team' => $SkinDb['weapon_team'],
                ];
            }

            $this->Db->query('Skins', $POST['id_server'], 0, $query, $params);

            return ['status' => 'success', 'text' => "{$this->Translate('_skins_yoyo')} {$skinsData['skins'][$POST['id_skin']]['name']}"];
        } else if ($this->Settings('type') == 2) {

            $SkinDb = $this->Db->query('Skins', 0, 0, "SELECT * FROM `sc_skins` WHERE `player_id` = :player_id AND `weapon_index` = :weapon_index AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'weapon_index' => $POST['weapon_index'],
                'server_id' => $POST['id_server'],
                'team' => $POST['id_team'],
            ]);

            if (empty($SkinDb['weapon_index'])) {
                $query = "INSERT INTO `sc_skins` (`player_id`, `server_id`, `team`, `weapon_index`, `stattrack`, `stattrack_count`, `stickers`, `skin`, `tag`) 
                      VALUES(:player_id, :server_id, :team, :weapon_index, :stattrack, :stattrack_count, :stickers, :skin, :tag)";
                $params = [
                    "player_id" => $this->DataDBUser(),
                    "server_id" => $POST['id_server'],
                    "skin" => "{$POST['id_skin']};0;0.0",
                    "team" => $POST['id_team'],
                    "weapon_index" => $POST['weapon_index'],
                    "stattrack" => !empty($SkinDb['stattrack']) ? $SkinDb['stattrack'] : '0',
                    "stattrack_count" => !empty($SkinDb['stattrack_count']) ? $SkinDb['stattrack_count'] : '0',
                    "stickers" => !empty($SkinDb['stickers']) ? $SkinDb['stickers'] : '0;0;0;0',
                    "skin" => "{$POST['id_skin']};0;0.0",
                    "tag" => !empty($SkinDb['tag']) ? $SkinDb['tag'] : ''
                ];
            } else {
                $query = "UPDATE `sc_skins` SET `skin` = :skin WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team AND `weapon_index` = :weapon_index LIMIT 1";
                $params = [
                    "player_id" => $SkinDb['player_id'],
                    "server_id" => $SkinDb['server_id'],
                    "team" => $SkinDb['team'],
                    "weapon_index" => $SkinDb['weapon_index'],
                    "skin" => "{$POST['id_skin']};0;0.0",
                ];
            }

            $this->Db->query('Skins', 0, 0, $query, $params);

            return ['status' => 'success', 'text' => "{$this->Translate('_skins_yoyo')} {$skinsData['skins'][$POST['id_skin']]['name']}"];
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;

            $SkinDb = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT * FROM `wp_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :teamwp LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_defindex' => $POST['weapon_index'],
                'teamwp' => $teamwp,
            ]);
            $weapon_wear = !empty($SkinDb['weapon_wear']) ? $SkinDb['weapon_wear'] : 0;
            $weapon_seed = !empty($SkinDb['weapon_seed']) ? $SkinDb['weapon_seed'] : 0;

            if (empty($SkinDb['weapon_defindex'])) {
                $query = "INSERT INTO `wp_skins` (`steamid`, `weapon_team`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`) 
                      VALUES(:steamid, :weapon_team, :weapon_defindex, :weapon_paint_id, :weapon_wear, :weapon_seed)";
                $params = [
                    'steamid' => $this->DataDBUser(),
                    'weapon_team' => $teamwp,
                    'weapon_defindex' => $POST['weapon_index'],
                    'weapon_paint_id' => $POST['id_skin'],
                    'weapon_wear' => $weapon_wear,
                    'weapon_seed' => $weapon_seed,
                ];
            } else {
                $query = "UPDATE `wp_skins` SET `weapon_paint_id` = :weapon_paint_id WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1";
                $params = [
                    'weapon_paint_id' => $POST['id_skin'],
                    'steamid' => $SkinDb['steamid'],
                    'weapon_defindex' => $SkinDb['weapon_defindex'],
                    'weapon_team' => $SkinDb['weapon_team'],
                ];
            }

            $this->Db->query('Skins', $POST['id_server'], 0, $query, $params);

            return ['status' => 'success', 'text' => "{$this->Translate('_skins_yoyo')} {$skinsData['skins'][$POST['id_skin']]['name']}"];
        } else {
            return ['status' => 'error', 'text' => $this->Translate('_errorskins')];
        }
    }

    public function SkinChangerNoSkin($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['weapon_index']) || intval($POST['weapon_index']) != $POST['weapon_index'] || $POST['weapon_index'] < 0) return ['html' => $this->Translate('_nonono')];

        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];
        if ($this->TableSearch()) return ['status' => 'error', 'text' => $this->Translate('_adm_table_no')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];
        if (!isset($POST['id_server'])) return ['status' => 'error', 'text' => $this->Translate('_serv_right')];
        if (!isset($POST['id_team'])) return ['status' => 'error', 'text' => $this->Translate('_command_left')];

        $skinsData = $this->get_cache($this->skins);
        $skinsData = array_filter($skinsData, function ($skin) use ($POST) {
            return $skin['id'] == $POST['weapon_index'];
        });
        $skinsData = reset($skinsData);

        if (empty($skinsData['name'])) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_player_skins` SET `weapon_paint_id` = '0', `weapon_sticker_0` = '0;0;0;0;0;0;0', `weapon_sticker_1` = '0;0;0;0;0;0;0',  `weapon_sticker_2` = '0;0;0;0;0;0;0',  `weapon_sticker_3` = '0;0;0;0;0;0;0',  `weapon_sticker_4` = '0;0;0;0;0;0;0', `weapon_keychain` = '0;0;0;0;0' WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                "steamid" => $this->DataDBUser(),
                "weapon_defindex" => $POST['weapon_index'],
                "weapon_team" => $teamwp,
            ]);
            return ['status' => 'success', 'text' => $this->Translate('_lolstok')];
        } else if ($this->Settings('type') == 2) {
            $this->Db->query('Skins', 0, 0, "UPDATE `sc_skins` SET `skin` = '0;0;0;0.0', `stickers` = '0;0;0;0', `keychain` = '0' WHERE `player_id` = :player_id AND `weapon_index` = :weapon_index AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                "player_id" => $this->DataDBUser(),
                "weapon_index" => $POST['weapon_index'],
                "server_id" => $POST['id_server'],
                "team" => $POST['id_team'],
            ]);
            return ['status' => 'success', 'text' => $this->Translate('_lolstok')];
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_skins` SET `weapon_paint_id` = '0', `weapon_sticker_0` = '0;0;0;0;0;0;0', `weapon_sticker_1` = '0;0;0;0;0;0;0',  `weapon_sticker_2` = '0;0;0;0;0;0;0',  `weapon_sticker_3` = '0;0;0;0;0;0;0',  `weapon_sticker_4` = '0;0;0;0;0;0;0', `weapon_keychain` = '0;0;0;0;0' WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                "steamid" => $this->DataDBUser(),
                "weapon_defindex" => $POST['weapon_index'],
                "weapon_team" => $teamwp,
            ]);
            return ['status' => 'success', 'text' => $this->Translate('_lolstok')];
        } else {
            return ['status' => 'error', 'text' => $this->Translate('_errorizmen')];
        }
    }

    public function SkinChangerAddKnife($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_knife']) || intval($POST['id_knife']) != $POST['id_knife'] || $POST['id_knife'] < 0) return ['html' => $this->Translate('_nonono')];

        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];
        if ($this->TableSearch()) return ['status' => 'error', 'text' => $this->Translate('_adm_table_no')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];
        if (!isset($POST['id_server'])) return ['status' => 'error', 'text' => $this->Translate('_serv_right')];
        if (!isset($POST['id_team'])) return ['status' => 'error', 'text' => $this->Translate('_command_left')];

        $skinsData = $this->get_cache($this->skins);
        $skinsData = array_filter($skinsData, function ($skin) use ($POST) {
            return $skin['id'] == $POST['id_knife'] && $skin['type'] == "Knife";
        });
        $skinsData = reset($skinsData);
        if (empty($skinsData['name'])) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $Knife = $this->get_cache($this->skins);
            $Knife = array_filter($Knife, function ($knife) use ($POST) {
                return $knife['id'] == $POST['id_knife'];
            });
            $Knife = reset($Knife);
            $Knifes = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT `knife` FROM `wp_player_knife` WHERE `steamid` = :steamid  AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_team' => $teamwp,
            ]);
            if (!empty($Knifes)) {
                $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_player_knife` SET `knife` = :knife WHERE `steamid` = :steamid AND `weapon_team` = :weapon_team LIMIT 1", [
                    "steamid" => $this->DataDBUser(),
                    "knife" => $Knife['id_name'],
                    "weapon_team" => $teamwp,
                ]);
            } else {
                $this->Db->query('Skins', $POST['id_server'], 0, "INSERT INTO `wp_player_knife` (`steamid`, `weapon_team`, `knife`) VALUES (:steamid, :weapon_team, :knife)", [
                    "steamid" => $this->DataDBUser(),
                    "knife" => $Knife['id_name'],
                    "weapon_team" => $teamwp,
                ]);
            }
            return ['status' => 'success', 'text' => "{$this->Translate('_knife_yoyo')} {$skinsData['name']}"];
        } else if ($this->Settings('type') == 2) {
            $sc_items = $this->Db->query('Skins', 0, 0, "SELECT `id` FROM `sc_items` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $POST['id_server'],
                'team' => $POST['id_team'],
            ]);
            if (!empty($sc_items)) {
                $this->Db->query('Skins', 0, 0, "UPDATE `sc_items` SET `knife` = :knife WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                    "player_id" => $this->DataDBUser(),
                    "knife" => $POST['id_knife'],
                    "server_id" => $POST['id_server'],
                    "team" => $POST['id_team'],
                ]);
            } else {
                $this->Db->query('Skins', 0, 0, "INSERT INTO `sc_items` (`player_id`, `server_id`, `team`, `agent`, `music`, `coin`, `knife`, `glove`) 
                    VALUES (:player_id, :server_id, :team, 0, 0, 0, :id_knife, 0)", [
                    "player_id" => $this->DataDBUser(),
                    "server_id" => $POST['id_server'],
                    "team" => $POST['id_team'],
                    "id_knife" => $POST['id_knife'],
                ]);
            }
            return ['status' => 'success', 'text' => "{$this->Translate('_knife_yoyo')} {$skinsData['name']}"];
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $Knife = $this->get_cache($this->skins);
            $Knife = array_filter($Knife, function ($knife) use ($POST) {
                return $knife['id'] == $POST['id_knife'];
            });
            $Knife = reset($Knife);
            $Knifes = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT `knife` FROM `wp_knife` WHERE `steamid` = :steamid  AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_team' => $teamwp,
            ]);
            if (!empty($Knifes)) {
                $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_knife` SET `knife` = :knife WHERE `steamid` = :steamid AND `weapon_team` = :weapon_team LIMIT 1", [
                    "steamid" => $this->DataDBUser(),
                    "knife" => $Knife['id_name'],
                    "weapon_team" => $teamwp,
                ]);
            } else {
                $this->Db->query('Skins', $POST['id_server'], 0, "INSERT INTO `wp_knife` (`steamid`, `weapon_team`, `knife`) VALUES (:steamid, :weapon_team, :knife)", [
                    "steamid" => $this->DataDBUser(),
                    "knife" => $Knife['id_name'],
                    "weapon_team" => $teamwp,
                ]);
            }
            return ['status' => 'success', 'text' => "{$this->Translate('_knife_yoyo')} {$skinsData['name']}"];
        } else {
            return ['status' => 'error', 'text' => $this->Translate('_errorizmen')];
        }
    }

    public function SkinChangerAddGlove($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_glove']) || intval($POST['id_glove']) != $POST['id_glove'] || $POST['id_glove'] < 0) return ['html' => $this->Translate('_nonono')];

        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];
        if (!isset($POST['id_server'])) return ['status' => 'error', 'text' => $this->Translate('_serv_right')];
        if (!isset($POST['id_team'])) return ['status' => 'error', 'text' => $this->Translate('_command_left')];

        $skinsData = $this->get_cache($this->skins);
        $skinsData = array_filter($skinsData, function ($skin) use ($POST) {
            return $skin['id'] == $POST['id_glove'] && $skin['type'] == "Gloves";
        });
        $skinsData = reset($skinsData);
        if (empty($skinsData['name'])) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $Glove = $this->get_cache($this->skins);
            $Glove = array_filter($Glove, function ($glove) use ($POST) {
                return $glove['id'] == $POST['id_glove'];
            });
            $Glove = reset($Glove);
            $Gloves = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT `weapon_defindex` FROM `wp_player_gloves` WHERE `steamid` = :steamid  AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_team' => $teamwp,
            ]);
            if (!empty($Gloves)) {
                $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_player_gloves` SET `weapon_defindex` = :weapon_defindex WHERE `steamid` = :steamid AND `weapon_team` = :weapon_team LIMIT 1", [
                    "weapon_defindex" => $POST['id_glove'],
                    "steamid" => $this->DataDBUser(),
                    "weapon_team" => $teamwp,
                ]);
            } else {
                $this->Db->query('Skins', $POST['id_server'], 0, "INSERT INTO `wp_player_gloves` (`steamid`, `weapon_team`, `weapon_defindex`) 
                    VALUES (:steamid, :weapon_team, :weapon_defindex)", [
                    'steamid' => $this->DataDBUser(),
                    'weapon_team' => $teamwp,
                    'weapon_defindex' => $POST['id_glove'],
                ]);
            }

            return ['status' => 'success', 'text' => "{$this->Translate('_gl_yoyo')} {$skinsData['name']}"];
        }
        if ($this->Settings('type') == 2) {
            $sc_items = $this->Db->query('Skins', 0, 0, "SELECT `id` FROM `sc_items` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $POST['id_server'],
                'team' => $POST['id_team'],
            ]);
            if (!empty($sc_items['id'])) {
                $this->Db->query('Skins', 0, 0, "UPDATE `sc_items` SET `glove` = :glove WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team LIMIT 1", [
                    "glove" => $POST['id_glove'],
                    "player_id" => $this->DataDBUser(),
                    "server_id" => $POST['id_server'],
                    "team" => $POST['id_team'],
                ]);
            } else {
                $this->Db->query('Skins', 0, 0, "INSERT INTO `sc_items` (`player_id`, `server_id`, `team`, `agent`, `music`, `coin`, `knife`, `glove`) 
                    VALUES (:player_id, :server_id, :team, 0, 0, 0, 0, :glove)", [
                    "player_id" => $this->DataDBUser(),
                    "server_id" => $POST['id_server'],
                    "team" => $POST['id_team'],
                    "glove" => $POST['id_glove'],
                ]);
            }
            return ['status' => 'success', 'text' => "{$this->Translate('_gl_yoyo')} {$skinsData['name']}"];
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $Glove = $this->get_cache($this->skins);
            $Glove = array_filter($Glove, function ($glove) use ($POST) {
                return $glove['id'] == $POST['id_glove'];
            });
            $Glove = reset($Glove);
            $Gloves = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT `weapon_defindex` FROM `wp_gloves` WHERE `steamid` = :steamid  AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_team' => $teamwp,
            ]);
            if (!empty($Gloves)) {
                $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_gloves` SET `weapon_defindex` = :weapon_defindex WHERE `steamid` = :steamid AND `weapon_team` = :weapon_team LIMIT 1", [
                    "weapon_defindex" => $POST['id_glove'],
                    "steamid" => $this->DataDBUser(),
                    "weapon_team" => $teamwp,
                ]);
            } else {
                $this->Db->query('Skins', $POST['id_server'], 0, "INSERT INTO `wp_gloves` (`steamid`, `weapon_team`, `weapon_defindex`) VALUES (:steamid, :weapon_team, :weapon_defindex)", [
                    'steamid' => $this->DataDBUser(),
                    'weapon_team' => $teamwp,
                    'weapon_defindex' => $POST['id_glove'],
                ]);
            }

            return ['status' => 'success', 'text' => "{$this->Translate('_gl_yoyo')} {$skinsData['name']}"];
        } else {
            return ['status' => 'error', 'text' => $this->Translate('_errorizmen')];
        }
    }

    public function StickerUpdate($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['weapon_index']) || intval($POST['weapon_index']) != $POST['weapon_index'] || $POST['weapon_index'] < 0) return ['html' => $this->Translate('_nonono')];

        if (empty($POST['id_slot'])) return ['status' => 'error', 'text' => $this->Translate('_no_slot')];
        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];
        if ($this->TableSearch()) return ['status' => 'error', 'text' => $this->Translate('_adm_table_no')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];
        if (!isset($POST['id_server'])) return ['status' => 'error', 'text' => $this->Translate('_serv_right')];
        if (!isset($POST['id_team'])) return ['status' => 'error', 'text' => $this->Translate('_command_left')];

        if ($POST['id_slot'] > 4) {
            return $this->KeychainsUpdate($POST);
        } else {
            $stickersCache = $this->get_cache($this->stickers);
        }

        $stickersData = $stickersCache[$POST['id_sticker']];
        if (!isset($stickersData)) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $stickers = $this->Db->query('Skins', 0, 0, "SELECT * FROM `wp_player_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_defindex' => $POST['weapon_index'],
                "weapon_team" => $teamwp,
            ]);

            if ($stickers['weapon_paint_id'] == 0) return ['status' => 'error', 'text' => $this->Translate('_no_stickers_gg')];

            switch ($POST['id_slot']) {
                case 4:
                    $column = "weapon_sticker_0";
                    $value  = "{$POST['id_sticker']};1;0;0;0;1;0";
                    break;
                case 3:
                    $column = "weapon_sticker_1";
                    $value  = "{$POST['id_sticker']};2;0;0;0;1;0";
                    break;
                case 2:
                    $column = "weapon_sticker_2";
                    $value  = "{$POST['id_sticker']};3;0;0;0;1;0";
                    break;
                case 1:
                    $column = "weapon_sticker_3";
                    $value  = "{$POST['id_sticker']};4;0;0;0;1;0";
                    break;
            }

            if (!empty($stickers['weapon_defindex'])) {
                $sql = "UPDATE `wp_player_skins` SET `$column` = :sticker_value WHERE `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team AND `steamid` = :steamid LIMIT 1";
                $this->Db->query('Skins', $POST['id_server'], 0, $sql, [
                    'sticker_value'   => $value,
                    'weapon_defindex' => $stickers['weapon_defindex'],
                    'weapon_team'     => $stickers['weapon_team'],
                    'steamid'         => $stickers['steamid'],
                ]);
            } else {
                $sql = "INSERT INTO `wp_player_skins` (`steamid`, `weapon_team`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`, `$column`) 
                        VALUES (:steamid, :weapon_team, :weapon_defindex, 0, 0, 0, :sticker_value)
                        ON DUPLICATE KEY UPDATE `$column` = VALUES(`$column`)";
                $this->Db->query('Skins', $POST['id_server'], 0, $sql, [
                    'steamid'         => $this->DataDBUser(),
                    'weapon_team'     => $teamwp,
                    'weapon_defindex' => $POST['weapon_index'],
                    'sticker_value'   => $value,
                ]);
            }
        } else if ($this->Settings('type') == 2) {
            $stickers = $this->Db->query('Skins', 0, 0, "SELECT * FROM `sc_skins` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team AND `weapon_index` = :weapon_index LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $POST['id_server'],
                'team' => $POST['id_team'],
                'weapon_index' => $POST['weapon_index'],
            ]);
            $explode = explode(';', $stickers['stickers']);

            $explode_wp = explode(';', $stickers['skin']);
            if ($explode_wp[0] == 0) return ['status' => 'error', 'text' => $this->Translate('_no_stickers_gg')];

            switch ($POST['id_slot']) {
                case 4:
                    $sticker = "{$POST['id_sticker']};{$explode[1]};{$explode[2]};{$explode[3]}";
                    break;
                case 3:
                    $sticker = "{$explode[0]};{$POST['id_sticker']};{$explode[2]};{$explode[3]}";
                    break;
                case 2:
                    $sticker = "{$explode[0]};{$explode[1]};{$POST['id_sticker']};{$explode[3]}";
                    break;
                case 1:
                    $sticker = "{$explode[0]};{$explode[1]};{$explode[2]};{$POST['id_sticker']}";
                    break;
            }

            if (!empty($stickers['weapon_index'])) {
                $this->Db->query('Skins', 0, 0, "UPDATE `sc_skins` SET `stickers` = :sticker WHERE `weapon_index` = :weapon_index AND `server_id` = :server_id AND `team` = :team AND `player_id` = :player_id LIMIT 1", [
                    "sticker" => $sticker,
                    "weapon_index" => $stickers['weapon_index'],
                    "server_id" => $stickers['server_id'],
                    "team" => $stickers['team'],
                    "player_id" => $stickers['player_id'],
                ]);
            } else {
                $this->Db->query('Skins', 0, 0, "INSERT INTO `sc_skins` (`player_id`, `server_id`, `team`, `weapon_index`, `stattrack`, `stattrack_count`, `stickers`, `skin`, `tag`) 
                    VALUES (:player_id, :server_id, :team, :weapon_index, '0', '0', :sticker, '0;0;0.0', '')", [
                    'player_id' => $this->DataDBUser(),
                    'server_id' => $POST['id_server'],
                    'team' => $POST['id_team'],
                    'weapon_index' => $POST['weapon_index'],
                    "sticker" => $sticker,
                ]);
            }
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $stickers = $this->Db->query('Skins', 0, 0, "SELECT * FROM `wp_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_defindex' => $POST['weapon_index'],
                "weapon_team" => $teamwp,
            ]);

            if ($stickers['weapon_paint_id'] == 0) return ['status' => 'error', 'text' => $this->Translate('_no_stickers_gg')];

            switch ($POST['id_slot']) {
                case 4:
                    $column = "weapon_sticker_0";
                    $value  = "{$POST['id_sticker']};1;0;0;0;1;0";
                    break;
                case 3:
                    $column = "weapon_sticker_1";
                    $value  = "{$POST['id_sticker']};2;0;0;0;1;0";
                    break;
                case 2:
                    $column = "weapon_sticker_2";
                    $value  = "{$POST['id_sticker']};3;0;0;0;1;0";
                    break;
                case 1:
                    $column = "weapon_sticker_3";
                    $value  = "{$POST['id_sticker']};4;0;0;0;1;0";
                    break;
            }

            if (!empty($stickers['weapon_defindex'])) {
                $sql = "UPDATE `wp_skins` SET `$column` = :sticker_value WHERE `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team AND `steamid` = :steamid LIMIT 1";
                $this->Db->query('Skins', $POST['id_server'], 0, $sql, [
                    'sticker_value'   => $value,
                    'weapon_defindex' => $stickers['weapon_defindex'],
                    'weapon_team'     => $stickers['weapon_team'],
                    'steamid'         => $stickers['steamid'],
                ]);
            } else {
                $sql = "INSERT INTO `wp_skins` (`steamid`, `weapon_team`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`, `$column`) 
                        VALUES (:steamid, :weapon_team, :weapon_defindex, 0, 0, 0, :sticker_value)
                        ON DUPLICATE KEY UPDATE `$column` = VALUES(`$column`)";
                $this->Db->query('Skins', $POST['id_server'], 0, $sql, [
                    'steamid'         => $this->DataDBUser(),
                    'weapon_team'     => $teamwp,
                    'weapon_defindex' => $POST['weapon_index'],
                    'sticker_value'   => $value,
                ]);
            }
        }

        return ['status' => 'success', 'text' => "{$this->Translate('_st_yoyo')} {$stickersData['name']}"];
    }

    public function KeychainsUpdate($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['weapon_index']) || intval($POST['weapon_index']) != $POST['weapon_index'] || $POST['weapon_index'] < 0) return ['html' => $this->Translate('_nonono')];

        if (empty($POST['id_slot'])) return ['status' => 'error', 'text' => $this->Translate('_no_slot')];
        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];
        if ($this->TableSearch()) return ['status' => 'error', 'text' => $this->Translate('_adm_table_no')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];
        if (!isset($POST['id_server'])) return ['status' => 'error', 'text' => $this->Translate('_serv_right')];
        if (!isset($POST['id_team'])) return ['status' => 'error', 'text' => $this->Translate('_command_left')];

        $keychainsCache = $this->get_cache($this->keychains);
        $keychainsData = $keychainsCache[$POST['id_sticker']];
        if (!isset($keychainsData)) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        $keychains_positions = [
            1 => [8.0, 0.0, 3.0],
            2 => [10.0, 0.0, 9.2],
            3 => [8.0, 0.0, 1.0],
            4 => [5.7, 0.0, 2.2],
            7 => [6.0, 0.0, 2.0],
            8 => [6.7, 5.0, 3.2],
            9 => [10.0, 0.0, 8.0],
            10 => [7.0, 0.0, 3.0],
            11 => [20.0, 0.0, 5.6],
            13 => [8.5, 1.0, 3.0],
            14 => [4.2, 0.0, 4.8],
            16 => [18.6, 7.0, 12.4],
            17 => [9.0, 0.0, 5.6],
            19 => [8.7, 0.0, 5.9],
            23 => [5.5, 0.0, 3.7],
            24 => [9.0, 0.0, 3.8],
            25 => [4.5, 0.0, 3.7],
            26 => [2.4, 0.0, 2.7],
            27 => [3.8, 0.0, 8.7],
            28 => [1.2, 0.0, 3.8],
            29 => [5.2, 0.0, 0.8],
            30 => [10.1, 5.0, 7.0],
            31 => [5.9, 5.0, 5.1],
            32 => [9.0, 0.0, 2.5],
            33 => [-3.0, 0.0, 2.5],
            34 => [9.0, 0.0, 5.6],
            35 => [6.5, 0.0, 3.7],
            36 => [8.0, 0.0, 1.0],
            38 => [20.0, 0.0, 12.2],
            39 => [7.5, 0.0, 4.0],
            40 => [10.0, 5.0, 3.8],
            60 => [6.0, 0.0, 4.5],
            61 => [7.0, 0.0, 4.0],
            63 => [6.8, 0.0, 4.7],
            64 => [9.0, 0.0, 4.0],
        ];

        $keychain = $_POST['id_sticker'];
        $keychain_x = $keychains_positions[$POST['weapon_index']][0];
        $keychain_y = $keychains_positions[$POST['weapon_index']][1];
        $keychain_z = $keychains_positions[$POST['weapon_index']][2];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $keychains = $this->Db->query('Skins', 0, 0, "SELECT * FROM `wp_player_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_defindex' => $POST['weapon_index'],
                'weapon_team' => $teamwp,
            ]);

            if ($keychains['weapon_paint_id'] == 0) return ['status' => 'error', 'text' => $this->Translate('_no_stickers_gg')];

            $keychain = "{$keychain};{$keychain_x};{$keychain_y};{$keychain_z};0";

            if (!empty($keychains['weapon_defindex'])) {
                $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_player_skins` SET `weapon_keychain` = :weapon_keychain WHERE `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team AND `steamid` = :steamid LIMIT 1", [
                    "weapon_keychain" => $keychain,
                    "weapon_defindex" => $keychains['weapon_defindex'],
                    "weapon_team" => $keychains['weapon_team'],
                    "steamid" => $keychains['steamid'],
                ]);
            } else {
                $this->Db->query('Skins', $POST['id_server'], 0, "INSERT INTO `wp_player_skins`(`steamid`, `weapon_team`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`) 
                    VALUES (:steamid, :weapon_team, :weapon_defindex, 0, 0, 0)", [
                    'steamid' => $this->DataDBUser(),
                    'weapon_defindex' => $POST['weapon_index'],
                    'weapon_team' => $teamwp,
                ]);
            }
        } else if ($this->Settings('type') == 2) {
            $keychains = $this->Db->query('Skins', 0, 0, "SELECT * FROM `sc_skins` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team AND `weapon_index` = :weapon_index LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $POST['id_server'],
                'team' => $POST['id_team'],
                'weapon_index' => $POST['weapon_index'],
            ]);

            $explode_wp = explode(';', $keychains['skin']);
            if ($explode_wp[0] == 0) return ['status' => 'error', 'text' => $this->Translate('_no_keychain_gg')];

            $keychain = "{$keychain};{$keychain_x};{$keychain_y};{$keychain_z}";

            if (!empty($keychains['weapon_index'])) {
                $this->Db->query('Skins', 0, 0, "UPDATE `sc_skins` SET `keychain` = :keychain WHERE `weapon_index` = :weapon_index AND `server_id` = :server_id AND `team` = :team AND `player_id` = :player_id LIMIT 1", [
                    "keychain" => $keychain,
                    "weapon_index" => $keychains['weapon_index'],
                    "server_id" => $keychains['server_id'],
                    "team" => $keychains['team'],
                    "player_id" => $keychains['player_id'],
                ]);
            } else {
                $this->Db->query('Skins', 0, 0, "INSERT INTO `sc_skins` (`player_id`, `server_id`, `team`, `weapon_index`, `stattrack`, `stattrack_count`, `stickers`, `skin`, `tag`, `keychain`) 
                    VALUES (:player_id, :server_id, :id_team, :weapon_index, '0', '0', '0;0;0;0', '0;0;0.0', :keychain)", [
                    'player_id' => $this->DataDBUser(),
                    'server_id' => $POST['id_server'],
                    'id_team' => $POST['id_team'],
                    'weapon_index' => $POST['weapon_index'],
                    "keychain" => $keychain,
                ]);
            }
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $keychains = $this->Db->query('Skins', 0, 0, "SELECT * FROM `wp_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                'steamid' => $this->DataDBUser(),
                'weapon_defindex' => $POST['weapon_index'],
                'weapon_team' => $teamwp,
            ]);

            if ($keychains['weapon_paint_id'] == 0) return ['status' => 'error', 'text' => $this->Translate('_no_stickers_gg')];

            $keychain = "{$keychain};{$keychain_x};{$keychain_y};{$keychain_z};0";

            if (!empty($keychains['weapon_defindex'])) {
                $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_skins` SET `weapon_keychain` = :weapon_keychain WHERE `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team AND `steamid` = :steamid LIMIT 1", [
                    "weapon_keychain" => $keychain,
                    "weapon_defindex" => $keychains['weapon_defindex'],
                    "weapon_team" => $keychains['weapon_team'],
                    "steamid" => $keychains['steamid'],
                ]);
            } else {
                $this->Db->query('Skins', $POST['id_server'], 0, "INSERT INTO `wp_skins`(`steamid`, `weapon_team`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`) 
                    VALUES (:steamid, :weapon_team, :weapon_defindex, 0, 0, 0)", [
                    'steamid' => $this->DataDBUser(),
                    'weapon_defindex' => $POST['weapon_index'],
                    'weapon_team' => $teamwp,
                ]);
            }
        }

        return ['status' => 'success', 'text' => "{$this->Translate('_st_yoyo')} {$keychainsData['name']}"];
    }

    public function SkinChangerSetting($POST)
    {
        if (!is_numeric($POST['id_server']) || intval($POST['id_server']) != $POST['id_server'] || $POST['id_server'] < 0 || ($this->Settings('type') == 1 || $this->Settings('type') == 3) && $POST['id_server'] >= $this->Db->table_count['Skins']) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['id_team']) || intval($POST['id_team']) != $POST['id_team'] || $POST['id_team'] > 2 || $POST['id_team'] < 0) return ['status' => 'error', 'text' => $this->Translate('_nonono')];
        if (!is_numeric($POST['weapon_index']) || intval($POST['weapon_index']) != $POST['weapon_index'] || $POST['weapon_index'] < 0) return ['html' => $this->Translate('_nonono')];

        if (!isset($_SESSION['steamid64'])) return ['status' => 'error', 'text' => $this->Translate('_no_auth')];
        if ($this->TableSearch()) return ['status' => 'error', 'text' => $this->Translate('_adm_table_no')];
        if ($this->TableSearchServer()) return ['status' => 'error', 'text' => $this->Translate('_adm_no_serv')];
        if (!isset($POST['id_server'])) return ['status' => 'error', 'text' => $this->Translate('_serv_right')];
        if (!isset($POST['id_team'])) return ['status' => 'error', 'text' => $this->Translate('_command_left')];

        $skinsData = $this->get_cache($this->skins);
        $skinsData = array_filter($skinsData, function ($skin) use ($POST) {
            return $skin['id'] == $POST['weapon_index'];
        });
        $skinsData = reset($skinsData);

        if (empty($skinsData['name'])) return ['status' => 'error', 'text' => $this->Translate('_nonono')];

        $stattrack_count = isset($_POST['stattrack_count']) ? round($_POST['stattrack_count']) : null;

        if ($_POST['float'] !== '' && (filter_var($_POST['float'], FILTER_VALIDATE_FLOAT) === false || $_POST['float'] < 0 || $_POST['float'] > 0.9999)) {
            return ['status' => 'error', 'text' => $this->Translate('_fl_0_999')];
        }

        if ($_POST['pattern'] !== '' && (filter_var($_POST['pattern'], FILTER_VALIDATE_INT) === false || $_POST['pattern'] < 0 || $_POST['pattern'] > 999)) {
            return ['status' => 'error', 'text' => $this->Translate('_pt_0_999')];
        }

        $stattrack_count_number = ($stattrack_count !== null && $stattrack_count !== '') ? $stattrack_count : null;
        if ($stattrack_count_number !== null && (filter_var($stattrack_count_number, FILTER_VALIDATE_INT) === false || $stattrack_count_number < 0 || $stattrack_count_number > 999999)) {
            return ['status' => 'error', 'text' => $this->Translate('_st_0_999999')];
        }

        if (mb_strlen($_POST['nametag']) > 20) return ['status' => 'error', 'text' => $this->Translate('_name_0_20')];

        if ($this->Settings('type') == 1) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $skin_active = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT * FROM `wp_player_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                "steamid" => $this->DataDBUser(),
                "weapon_defindex" => $POST['weapon_index'],
                "weapon_team" => $teamwp,
            ]);

            $weapon_seed = !empty($POST['pattern']) ? $POST['pattern'] : 0;
            $weapon_wear = !empty($POST['float']) ? $POST['float'] : 0.0;

            if (!empty($skin_active['weapon_defindex'])) {
                $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_player_skins` SET `weapon_nametag` = :weapon_nametag, `weapon_stattrak` = :weapon_stattrak, `weapon_stattrak_count` = :weapon_stattrak_count, `weapon_seed` = :weapon_seed, `weapon_wear` = :weapon_wear WHERE `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team AND `steamid` = :steamid LIMIT 1", [
                    'weapon_nametag' => $POST['nametag'],
                    'weapon_stattrak' => $POST['stattrack'],
                    'weapon_stattrak_count' => $stattrack_count_number,
                    'weapon_seed' => $weapon_seed,
                    'weapon_wear' => $weapon_wear,
                    'weapon_defindex' => $skin_active['weapon_defindex'],
                    'weapon_team' => $skin_active['weapon_team'],
                    'steamid' => $skin_active['steamid'],
                ]);
            } else {
                $this->Db->query('Skins', $POST['id_server'], 0, "INSERT INTO `wp_player_skins`(`steamid`, `weapon_team`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`) 
                    VALUES (:steamid, :weapon_team, :weapon_defindex, 0, :weapon_wear, :weapon_seed)", [
                    "steamid" => $this->DataDBUser(),
                    "weapon_team" => $teamwp,
                    "weapon_defindex" => $POST['weapon_index'],
                    "weapon_wear" => $weapon_wear,
                    "weapon_seed" => $weapon_seed,
                ]);
            }
            return ['status' => 'success', 'text' => $this->Translate('_save_md_set')];
        } else if ($this->Settings('type') == 2) {

            $skin_active = $this->Db->query('Skins', 0, 0, "SELECT * FROM `sc_skins` WHERE `player_id` = :player_id AND `server_id` = :server_id AND `team` = :team AND `weapon_index` = :weapon_index LIMIT 1", [
                'player_id' => $this->DataDBUser(),
                'server_id' => $POST['id_server'],
                'team' => $POST['id_team'],
                'weapon_index' => $POST['weapon_index'],
            ]);

            $explode = explode(';', $skin_active['skin'])[0] ?: 0;

            $pattern = !empty($POST['pattern']) ? $POST['pattern'] : 0;
            $float = !empty($POST['float']) ? $POST['float'] : 0.0;

            $skin = "{$explode};{$pattern};{$float}";

            if (!empty($skin_active['weapon_index'])) {
                $this->Db->query('Skins', 0, 0, "UPDATE `sc_skins` SET `stattrack` = :stattrack, `stattrack_count` = :stattrack_count, `skin` = :skin, `tag` = :tag WHERE `weapon_index` = :weapon_index AND `server_id` = :server_id AND `team` = :team AND `player_id` = :player_id LIMIT 1", [
                    "stattrack" => $POST['stattrack'],
                    "stattrack_count" => $stattrack_count,
                    "skin" => $skin,
                    "tag" => $POST['nametag'],
                    "weapon_index" => $skin_active['weapon_index'],
                    "server_id" => $skin_active['server_id'],
                    "team" => $skin_active['team'],
                    "player_id" => $skin_active['player_id']
                ]);
            } else {
                $this->Db->query('Skins', 0, 0, "INSERT INTO `sc_skins` (`player_id`, `server_id`, `team`, `weapon_index`, `stattrack`, `stattrack_count`, `stickers`, `skin`, `tag`) 
                    VALUES (:player_id, :server_id, :team, :weapon_index, :stattrack, :stattrack_count, '0;0;0;0', :skin, :tag)", [
                    'player_id' => $this->DataDBUser(),
                    'server_id' => $POST['id_server'],
                    'team' => $POST['id_team'],
                    'weapon_index' => $POST['weapon_index'],
                    'stattrack' => $POST['stattrack'],
                    'stattrack_count' => $stattrack_count,
                    'skin' => $skin,
                    'tag' => $POST['nametag'],
                ]);
            }
            return ['status' => 'success', 'text' => $this->Translate('_save_md_set')];
        } else if ($this->Settings('type') == 3) {
            $teamwp = empty($POST['id_team']) ? 2 : 3;
            $skin_active = $this->Db->query('Skins', $POST['id_server'], 0, "SELECT * FROM `wp_skins` WHERE `steamid` = :steamid AND `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team LIMIT 1", [
                "steamid" => $this->DataDBUser(),
                "weapon_defindex" => $POST['weapon_index'],
                "weapon_team" => $teamwp,
            ]);

            $weapon_seed = !empty($POST['pattern']) ? $POST['pattern'] : 0;
            $weapon_wear = !empty($POST['float']) ? $POST['float'] : 0.0;

            if (!empty($skin_active['weapon_defindex'])) {
                $this->Db->query('Skins', $POST['id_server'], 0, "UPDATE `wp_skins` SET `weapon_nametag` = :weapon_nametag, `weapon_stattrak` = :weapon_stattrak, `weapon_stattrak_count` = :weapon_stattrak_count, `weapon_seed` = :weapon_seed, `weapon_wear` = :weapon_wear WHERE `weapon_defindex` = :weapon_defindex AND `weapon_team` = :weapon_team AND `steamid` = :steamid LIMIT 1", [
                    'weapon_nametag' => $POST['nametag'],
                    'weapon_stattrak' => $POST['stattrack'],
                    'weapon_stattrak_count' => $stattrack_count_number,
                    'weapon_seed' => $weapon_seed,
                    'weapon_wear' => $weapon_wear,
                    'weapon_defindex' => $skin_active['weapon_defindex'],
                    'weapon_team' => $skin_active['weapon_team'],
                    'steamid' => $skin_active['steamid'],
                ]);
            } else {
                $this->Db->query('Skins', $POST['id_server'], 0, "INSERT INTO `wp_skins`(`steamid`, `weapon_team`, `weapon_defindex`, `weapon_paint_id`, `weapon_wear`, `weapon_seed`) 
                    VALUES (:steamid, :weapon_team, :weapon_defindex, 0, :weapon_wear, :weapon_seed)", [
                    "steamid" => $this->DataDBUser(),
                    "weapon_team" => $teamwp,
                    "weapon_defindex" => $POST['weapon_index'],
                    "weapon_wear" => $weapon_wear,
                    "weapon_seed" => $weapon_seed,
                ]);
            }
            return ['status' => 'success', 'text' => $this->Translate('_save_md_set')];
        } else {
            return ['status' => 'error', 'text' => $this->Translate('_adm_tyty')];
        }
    }
}
