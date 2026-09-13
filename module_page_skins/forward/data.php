<?php

use app\modules\module_page_skins\system\FunctionCore;

$Function = new FunctionCore($Db, $General, $Translate);

if ($Function->Settings('work') == "1" and !isset($_SESSION['user_admin'])) {
    get_iframe("404", "Технические работы");
}

$Router->map('GET|POST', 'skins/[weapons|agents|coins|music|adminpanel:page]/', 'weapons');

$Map = $Router->match();

$page = $Map['params']['page'] ?? 'weapons';

if (!isset($_SESSION['user_admin'])) {
    if ($page == 'adminpanel') {
        get_iframe("404", "Доступ для Администратора");
    }
}

if (!empty($Db->db_data['Skins'])) {
    if (!isset($_SESSION['user_admin']) && $Function->isSkinsAdminPost($_POST)) {
        $Function->exitJsonAdminDenied();
    }
    switch (true) {
        case (isset($_POST['html_weapons'])):
            $search_text = (string) htmlentities($_POST['search_text']);
            exit(json_encode($Function->Weapons()->IntefaceWP($_POST['id_sort'], $_POST['id_server'], $_POST['id_team'], $search_text), true));
        case (isset($_POST['SkinChangerUpdate'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Weapons()->SkinChangerUpdate($_POST), true));
        case (isset($_POST['SkinChangerSettingModal'])):
            exit(json_encode($Function->Weapons()->SkinChangerSettingModal($_POST), true));
        case (isset($_POST['SkinChangerSetting'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Weapons()->SkinChangerSetting($_POST), true));
        case (isset($_POST['SkinChangerAddKnife'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Weapons()->SkinChangerAddKnife($_POST), true));
        case (isset($_POST['SkinChangerAddGlove'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Weapons()->SkinChangerAddGlove($_POST), true));
        case (isset($_POST['SkinChangerNoSkin'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Weapons()->SkinChangerNoSkin($_POST), true));
        case (isset($_POST['SkinChangerStickers'])):
            exit(json_encode($Function->Weapons()->SkinChangerStickers($_POST), true));
        case (isset($_POST['StickerUpdate'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Weapons()->StickerUpdate($_POST), true));
        case (isset($_POST['id_name'])):
            exit(json_encode($Function->Weapons()->ModalWP($_POST), true));
        case (isset($_POST['StickersKeychainHtml'])):
            exit(json_encode($Function->Weapons()->StickersKeychainHtml($_POST), true));
        case (isset($_POST['html_agents'])):
            $search_text = (string) htmlentities($_POST['search_text']);
            exit(json_encode($Function->Agents()->IntefaceAgents($_POST['id_server'], $_POST['id_team'], $search_text), true));
        case (isset($_POST['set_agent'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Agents()->SCSkinChangerAgents($_POST['type_agent'], $_POST['id_server'], $_POST['id_team'], $_POST['id_agent']), true));
        case (isset($_POST['html_coins'])):
            $search_text = (string) htmlentities($_POST['search_text']);
            exit(json_encode($Function->Coins()->IntefaceCoins($_POST['id_server'], $_POST['id_team'], $search_text), true));
        case (isset($_POST['set_coins'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Coins()->SCSkinChangerCoins($_POST['type_coins'], $_POST['id_server'], $_POST['id_team'], $_POST['id_coins']), true));
        case (isset($_POST['html_music'])):
            $search_text = (string) htmlentities($_POST['search_text']);
            exit(json_encode($Function->Music()->IntefaceMusic($_POST['id_server'], $_POST['id_team'], $search_text), true));
        case (isset($_POST['set_music'])):
            $antispam_check = $Function->AntiSpam();
            if ($antispam_check['status'] === 'error') return exit(json_encode($antispam_check));
            exit(json_encode($Function->Music()->SCSkinChangerMusic($_POST['type_music'], $_POST['id_server'], $_POST['id_team'], $_POST['id_music']), true));
        case (isset($_POST['UpdateCache'])):
            exit(json_encode($Function->Cache()->UpdateCache(), true));
        case (isset($_POST['CacheSkins'])):
            exit(json_encode($Function->Cache()->CacheSkins(), true));
        case (isset($_POST['CacheStickers'])):
            exit(json_encode($Function->Cache()->CacheStickers(), true));
        case (isset($_POST['CacheKeychains'])):
            exit(json_encode($Function->Cache()->CacheKeychains(), true));
        case (isset($_POST['CacheAgents'])):
            exit(json_encode($Function->Cache()->CacheAgents(), true));
        case (isset($_POST['CacheMoney'])):
            exit(json_encode($Function->Cache()->CacheMoney(), true));
        case (isset($_POST['CacheMusic'])):
            exit(json_encode($Function->Cache()->CacheMusic(), true));
        case (isset($_POST['add_servers_sk'])):
            exit(json_encode($Function->SettingsModule($_POST, 1), true));
        case (isset($_POST['save_settings_sk'])):
            exit(json_encode($Function->SettingsModule($_POST, 2), true));
        case (isset($_POST['server_delete'])):
            exit(json_encode($Function->SettingsModule($_POST, 3), true));
    }
} else {
    if (isset($_POST['save_db'])) {
        if (!isset($_SESSION['user_admin'])) {
            $Function->exitJsonAdminDenied();
        }
        exit(json_encode($Function->AddDBSkins($_POST), true));
    }
}

$Modules->set_page_title("{$Function->Translate('_set_page_title')} | {$General->arr_general['short_name']}");

$Modules->set_page_description($Function->Translate('_set_page_description'));
