<?php

namespace app\modules\module_page_skins\system;

use app\modules\module_page_skins\system\FunctionCore;

class Cache extends FunctionCore
{
    protected $Db, $General, $Translate, $languages, $skins;

    protected $cacheLanguages = ['ru', 'en', 'de', 'uk'];
    protected $apiFallbackLanguage = 'en';
    protected $apiBaseUrl = 'https://raw.githubusercontent.com/ByMykel/CSGO-API/refs/heads/main/public/api';

    public function __construct($Db, $General, $Translate)
    {
        $this->Db            = $Db;
        $this->General       = $General;
        $this->Translate     = $Translate;
        $this->languages     = strtolower($_SESSION['language']);
    }

    protected function get_api_data($pathTemplate, $language)
    {
        $url = sprintf($pathTemplate, $language);
        $data = $this->get_cache($url);
        if (!empty($data) && is_array($data)) {
            return $data;
        }
        $fallbackUrl = sprintf($pathTemplate, $this->apiFallbackLanguage);
        return $this->get_cache($fallbackUrl) ?: [];
    }

    public function CacheSkins()
    {
        $languages = $this->cacheLanguages;
        function createSkinStructure($skinInfo)
        {
            return [
                "id_skin" => $skinInfo['paint_index'],
                "name" => $skinInfo["name"],
                "image" => $skinInfo["image"],
                "id_rarity" => $skinInfo["rarity"]['id'],
                "rarity" => $skinInfo["rarity"]['name']
            ];
        }

        foreach ($languages as $language) {

            $cacheFilePath = MODULES . "module_page_skins/cache/skins-{$language}.json";
            $SkinsCache = $this->get_cache(MODULES . "module_page_skins/cache/standard.json");
            $SkinsData = $this->get_api_data($this->apiBaseUrl . '/%s/skins.json', $language);

            foreach ($SkinsCache as &$cacheEntry) {
                $idName = $cacheEntry["id_name"];
                if (!isset($cacheEntry["skins"])) {
                    $cacheEntry["skins"] = [];
                }
                $selected_skins = array_filter($SkinsData, function ($skin) use ($idName) {
                    return $skin['weapon']['id'] == $idName;
                });
                foreach ($selected_skins as $Skin) {
                    $paintIndex = $Skin['paint_index'];
                    if (!isset($cacheEntry["skins"][$paintIndex])) {
                        $cacheEntry["skins"][$paintIndex] = createSkinStructure($Skin);
                    }
                }
            }

            $this->put_cache($cacheFilePath, $SkinsCache);
        }

        return ['status' => 'success', 'text' => $this->Translate('_yspex_ccc')];
    }

    public function CacheKeychains()
    {
        $languages = $this->cacheLanguages;

        $languages_text = [
            "ru" => "Брелок | Не выбран",
            "en" => "Keychain | Not selected",
            "de" => "Schlüsselanhänger | Nicht ausgewählt",
            "uk" => "Брелок | Не обрано"
        ];

        foreach ($languages as $language) {

            $cacheFilePath = MODULES . "module_page_skins/cache/keychains-{$language}.json";
            $KeychainsData = $this->get_api_data($this->apiBaseUrl . '/%s/keychains.json', $language);

            $newFormat = [];
            foreach ($KeychainsData as $cacheEntry) {
                if (!empty($cacheEntry['def_index'])) {
                    $newFormat[$cacheEntry['def_index']] = [
                        "name" => $cacheEntry["name"],
                        "image" => $cacheEntry["image"],
                        "id_rarity" => $cacheEntry["rarity"]['id'],
                        "rarity" => $cacheEntry["rarity"]['name'],
                    ];
                }
            }

            $customKeychain = [
                0 => [
                    "name" => $languages_text[$language] ?? $languages_text[$this->apiFallbackLanguage],
                    "image" => "/app/modules/module_page_skins/assets/img/sticker0.png",
                    "id_rarity" => "rarity_default",
                    "rarity" => "Пустой"
                ]
            ];

            $Keychains = $customKeychain + $newFormat;

            $this->put_cache($cacheFilePath, $Keychains);
        }

        return ['status' => 'success', 'text' => $this->Translate('_yspex_ccc')];
    }

    public function CacheStickers()
    {
        $languages = $this->cacheLanguages;

        $languages_text = [
            "ru" => "Наклейка | Не выбрано",
            "en" => "Sticker | Not selected",
            "de" => "Aufkleber | Nicht ausgewählt",
            "uk" => "Наклейка | Не обрано"
        ];

        foreach ($languages as $language) {

            $cacheFilePath = MODULES . "module_page_skins/cache/stickers-{$language}.json";
            $StickersData = $this->get_api_data($this->apiBaseUrl . '/%s/stickers.json', $language);

            $newFormat = [];
            foreach ($StickersData as $cacheEntry) {
                if (!empty($cacheEntry['def_index'])) {
                    $newFormat[$cacheEntry['def_index']] = [
                        "name" => $cacheEntry["name"],
                        "image" => $cacheEntry["image"],
                        "id_rarity" => $cacheEntry["rarity"]['id'],
                        "rarity" => $cacheEntry["rarity"]['name'],
                    ];
                }
            }

            $customSticker = [
                "0" => [
                    "name" => $languages_text[$language] ?? $languages_text[$this->apiFallbackLanguage],
                    "image" => "/app/modules/module_page_skins/assets/img/sticker0.png",
                    "id_rarity" => "rarity_default",
                    "rarity" => "Пустой"
                ]
            ];

            $Stickers = $customSticker + $newFormat;

            $this->put_cache($cacheFilePath, $Stickers);
        }

        return ['status' => 'success', 'text' => $this->Translate('_yspex_ccc')];
    }

    public function CacheAgents()
    {
        $languages = $this->cacheLanguages;

        function createAgentStructure($AgentInfo)
        {
            if (empty($AgentInfo['def_index'])) return null;

            if (preg_match('/characters\/models\/([^\/]+)\/([^\/]+)\.vmdl$/', $AgentInfo["model_player"], $matches)) {
                $NameGame = $matches[1];
                $ModelName = $matches[2];
                $modelPath = "{$NameGame}/{$ModelName}";
            } else {
                $modelPath = NULL;
            }

            return [
                "id" => $AgentInfo['def_index'],
                "name" => $AgentInfo["name"],
                "image" => $AgentInfo["image"],
                "model" => $modelPath,
                "team"  => ($AgentInfo["team"]['id'] == 'terrorists') ? 0 : 1,
                "id_rarity" => $AgentInfo["rarity"]['id'],
                "rarity" => $AgentInfo["rarity"]['name']
            ];
        }

        foreach ($languages as $language) {

            $cacheFilePath = MODULES . "module_page_skins/cache/agents-{$language}.json";
            $AgentsData = $this->get_api_data($this->apiBaseUrl . '/%s/agents.json', $language);

            foreach ($AgentsData as &$cacheEntry) {
                $cacheEntry = createAgentStructure($cacheEntry);
            }

            $this->put_cache($cacheFilePath, $AgentsData);
        }

        return ['status' => 'success', 'text' => $this->Translate('_yspex_ccc')];
    }

    public function CacheMusic()
    {
        $languages = $this->cacheLanguages;

        function createMusicStructure($MusicInfo)
        {
            if (empty($MusicInfo['def_index'])) return null;

            return [
                "id" => $MusicInfo['def_index'],
                "name" => $MusicInfo["name"],
                "image" => $MusicInfo["image"],
                "id_rarity" => $MusicInfo["rarity"]['id'],
                "rarity" => $MusicInfo["rarity"]['name']
            ];
        }

        foreach ($languages as $language) {

            $cacheFilePath = MODULES . "module_page_skins/cache/music-{$language}.json";
            $MusicData = $this->get_api_data($this->apiBaseUrl . '/%s/music_kits.json', $language);

            foreach ($MusicData as $key => &$cacheEntry) {
                $cacheEntryID = explode('-', $cacheEntry['id'])[1];
                if (stripos($cacheEntryID, "_st") !== false) {
                    unset($MusicData[$key]);
                } else {
                    $cacheEntry = createMusicStructure($cacheEntry);
                }
            }
            $this->put_cache($cacheFilePath, $MusicData);
        }

        return ['status' => 'success', 'text' => $this->Translate('_yspex_ccc')];
    }

    public function CacheMoney()
    {
        $languages = $this->cacheLanguages;

        function createMoneyStructure($MoneyInfo)
        {
            if (empty($MoneyInfo['def_index'])) return null;

            return [
                "id" => $MoneyInfo['def_index'],
                "name" => $MoneyInfo["name"],
                "image" => $MoneyInfo["image"],
                "type" => $MoneyInfo["type"],
                "id_rarity" => $MoneyInfo["rarity"]['id'],
                "rarity" => $MoneyInfo["rarity"]['name']
            ];
        }

        foreach ($languages as $language) {

            $cacheFilePath = MODULES . "module_page_skins/cache/collectibles-{$language}.json";
            $MoneyData = $this->get_api_data($this->apiBaseUrl . '/%s/collectibles.json', $language);

            foreach ($MoneyData as $key => &$cacheEntry) {
                if (stripos($cacheEntry["type"], "Pass") !== false || stripos($cacheEntry["type"], "Stars for Operation") !== false) {
                    unset($MoneyData[$key]);
                } else {
                    $cacheEntry = createMoneyStructure($cacheEntry);
                }
            }
            $this->put_cache($cacheFilePath, $MoneyData);
        }

        return ['status' => 'success', 'text' => $this->Translate('_yspex_ccc')];
    }
}
