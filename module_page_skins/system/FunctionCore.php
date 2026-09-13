<?php

namespace app\modules\module_page_skins\system;

use app\modules\module_page_skins\system\Rcon;
use app\modules\module_page_skins\system\Weapons;
use app\modules\module_page_skins\system\Agents;
use app\modules\module_page_skins\system\Music;
use app\modules\module_page_skins\system\Coins;
use app\modules\module_page_skins\system\Cache;
use Exception;

class FunctionCore extends Rcon
{
    protected $Db, $General, $Translate, $Weapons, $Agents, $Music, $Coins, $Cache;

    public function __construct($Db, $General, $Translate)
    {
        $this->Db            = $Db;
        $this->General       = $General;
        $this->Translate     = $Translate;
        $this->Weapons       = new Weapons($Db, $General, $Translate);
        $this->Agents        = new Agents($Db, $General, $Translate);
        $this->Music         = new Music($Db, $General, $Translate);
        $this->Coins         = new Coins($Db, $General, $Translate);
        $this->Cache         = new Cache($Db, $General, $Translate);
    }

    public function Cache()
    {
        return $this->Cache;
    }

    public function UpdateCache()
    {
        $this->Cache->CacheSkins();
        $this->Cache->CacheStickers();
        $this->Cache->CacheKeychains();
        $this->Cache->CacheAgents();
        $this->Cache->CacheMusic();
        $this->Cache->CacheMoney();
        return ['status' => 'success', 'text' => $this->Translate('_yspex_ccc_all')];
    }

    public function Weapons()
    {
        return $this->Weapons;
    }

    public function Agents()
    {
        return $this->Agents;
    }

    public function Music()
    {
        return $this->Music;
    }

    public function Coins()
    {
        return $this->Coins;
    }

    public function get_cache($id)
    {
        $packed_data = file_get_contents($id);
        return json_decode($packed_data, true);
    }

    public function put_cache($id, $arr)
    {
        $packed_data = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($id, $packed_data);
        return;
    }

    public function isSkinsAdminPost(array $post): bool
    {
        static $admin_keys = null;
        if ($admin_keys === null) {
            $admin_keys = array_flip([
                'UpdateCache', 'CacheSkins', 'CacheStickers', 'CacheKeychains', 'CacheAgents',
                'CacheMoney', 'CacheMusic', 'add_servers_sk', 'save_settings_sk', 'server_delete',
            ]);
        }

        return (bool) array_intersect_key($post, $admin_keys);
    }

    public function exitJsonAdminDenied(): void
    {
        exit(json_encode(['status' => 'error', 'text' => 'Доступ только для администратора'], JSON_UNESCAPED_UNICODE));
    }

    public function SCServers()
    {
        return $this->Db->queryAll('Skins', 0, 0, "SELECT * FROM `sc_servers`");
    }

    public function Settings($text)
    {
        return $this->get_cache(MODULES . "module_page_skins/settings.json")[$text];
    }

    public function ModuleStatus()
    {
        $type = $this->Settings('type');
        if ($type == 3) {
            if ($this->Db->mysql_table_search("Skins", 0, 0, 'wp_status')) {
                return $this->Db->query('Skins', 0, 0, "SELECT * FROM `wp_status` LIMIT 1");
            }
        }
        return null;
    }

    public function Translate($text)
    {
        if (function_exists($this->Translate->translate)) {
            return $this->Translate->translate('module_page_skins', $text);
        } else {
            return $this->Translate->get_translate_module_phrase('module_page_skins', $text);
        }
    }

    public function AddDBSkins($POST)
    {
        if (empty($POST['host']) || empty($POST['user']) || empty($POST['name_db']) || empty($POST['name_server'])) {
            return ['status' => 'error', 'text' => "Некоторые поля почему-то пустые..."];
        }

        $this->Db->change_db(
            "Skins",
            $POST['host'],
            $POST['name_db'],
            $POST['pass_db'],
            $POST['user'],
            '',
            0,
            [
                'table' => '',
                'name' => $POST['name_server'],
                'mod' => ''
            ]
        );

        return ['status' => 'success', 'text' => $this->Translate('_input_yeye')];
    }

    public function SettingsModule($POST, $type)
    {
        switch ($type) {
            case (1):
                $ip_server = explode(':', $POST['ip_server'], 2);
                if (empty($ip_server[0]) || !isset($ip_server[1]) || $ip_server[1] === '') {
                    return ['status' => 'error', 'text' => $this->Translate('_nonono')];
                }
                $server = $this->Db->query('Skins', 0, 0, "SELECT * FROM `sc_servers` WHERE `ip_address` = :ip_address AND `port` = :port LIMIT 1", [
                    'ip_address' => $ip_server[0],
                    'port' => $ip_server[1]
                ]);
                if (!empty($server['name'])) {
                    $this->Db->query('Skins', 0, 0, "UPDATE `sc_servers` SET `name` = :name WHERE `ip_address` = :ip_address AND `port` = :port LIMIT 1", [
                        'name' => $POST['name_server'],
                        'ip_address' => $server['ip_address'],
                        'port' => $ip_server[1]
                    ]);
                    return ['status' => 'success', 'text' => $this->Translate('_up_name')];
                } else {
                    $this->Db->query('Skins', 0, 0, "INSERT INTO `sc_servers`(`name`, `ip_address`, `port`) VALUES (:name, :ip_address, :port)", [
                        'name' => $POST['name_server'],
                        'ip_address' => $ip_server[0],
                        'port' => $ip_server[1]
                    ]);
                    return ['status' => 'success', 'text' => $this->Translate('_add_new')];
                }
            case (2):
                $settings = MODULES . "module_page_skins/settings.json";
                $setting_post = [
                    "type" => $POST['type_module'],
                    "work" => $POST['work_module'],
                    "buttons" => $POST['buttons_module'],
                    "team" => $POST['team_module'],
                ];
                $this->put_cache($settings, $setting_post);
                return ['status' => 'success', 'text' => $this->Translate('_save_md_set')];
            case (3):
                $server = $this->Db->query('Skins', 0, 0, "SELECT * FROM `sc_servers` WHERE `id` = :id_server LIMIT 1", [
                    'id_server' => $POST['id_server']
                ]);
                if (!empty($server['name'])) {
                    $this->Db->query('Skins', 0, 0, "DELETE FROM `sc_items` WHERE `server_id` = :server_id", [
                        'server_id' => $server['id']
                    ]);
                    $this->Db->query('Skins', 0, 0, "DELETE FROM `sc_skins` WHERE `server_id` = :server_id", [
                        'server_id' => $server['id']
                    ]);
                    sleep(1);
                    $this->Db->query('Skins', 0, 0, "DELETE FROM `sc_servers` WHERE `id` = :id_server LIMIT 1", [
                        'id_server' => $server['id']
                    ]);
                    return ['status' => 'success', 'text' => "{$this->Translate('_del_serv')} {$server['name']}"];
                } else {
                    return ['status' => 'error', 'text' => $this->Translate('_no_serv')];
                }
            default:
                return ['status' => 'error', 'text' => $this->Translate('_error')];
        }
    }

    public function TableSearch()
    {
        $type = $this->Settings('type');
        $tables = [];

        switch ($type) {
            case 1:
                $tables = ["wp_player_knife", "wp_player_skins", "wp_player_agents", "wp_player_gloves", "wp_player_music"];
                break;
            case 2:
                $tables = ["sc_items", "sc_player", "sc_servers", "sc_skins"];
                break;
            case 3:
                $tables = ["wp_knife", "wp_skins", "wp_agents", "wp_gloves", "wp_music"];
                break;
            default:
                break;
        }

        if (empty($tables)) {
            return true;
        }

        foreach ($tables as $table) {
            $result = $this->Db->mysql_table_search("Skins", 0, 0, $table);
            if ($result == 0) {
                return true;
            }
        }
        return false;
    }

    public function TableSearchServer()
    {
        $type = $this->Settings('type');
        if ($type == 2) {
            $result = $this->Db->query('Skins', 0, 0, "SELECT `ip_address` FROM `sc_servers` LIMIT 1");
            if (empty($result['ip_address'])) {
                return true;
            }
        }
        return false;
    }

    protected function Rcons($command)
    {
        try {
            $_Server_Info = $this->General->server_list;
            foreach ($_Server_Info as $server) {
                $_IP = explode(':', $server['ip']);
                $_RCON = new Rcon($_IP[0], $_IP[1]);
                if ($_RCON->Connect()) {
                    $_RCON->RconPass($server['rcon']);
                    $_RCON->Command($command);
                    $_RCON->Disconnect();
                }
            }
        } catch (Exception $e) {
            return ['status' => "error", 'text' => "RCON error: {$e->getMessage()}"];
        }
    }

    public function AntiSpam($max_requests = 30, $time_window = 60)
    {
        $ip = $this->General->get_client_ip_cdn();
        $cache_key = MODULES . 'module_page_skins/cache/spam/antispam_' . md5($ip);
        $current_time = time();

        $this->cleanupOldSpamFiles();

        $data = $this->get_cache($cache_key);

        if (!$data) {
            $data = [
                'count' => 1,
                'start_time' => $current_time
            ];
            $this->put_cache($cache_key, $data, $time_window);
            return [
                'status' => 'success',
                'remaining' => $max_requests - 1,
                'wait' => $time_window
            ];
        }

        if ($current_time - $data['start_time'] >= $time_window) {
            $data['count'] = 0;
            $data['start_time'] = $current_time;
        }

        $elapsed = $current_time - $data['start_time'];
        $wait = max(0, $time_window - $elapsed);

        if ($data['count'] >= $max_requests) {
            return [
                'status' => 'error',
                'text' => "{$this->Translate('_available_through')} {$wait} {$this->Translate('_seconds')}.",
                'wait' => $wait
            ];
        }

        $data['count']++;
        $this->put_cache($cache_key, $data, $time_window);

        return [
            'status' => 'success',
            'remaining' => $max_requests - $data['count'],
            'wait' => $wait
        ];
    }

    private function cleanupOldSpamFiles()
    {
        $spam_dir = MODULES . 'module_page_skins/cache/spam/';
        $current_time = time();
        $max_age = 3600;

        if (!is_dir($spam_dir)) {
            return;
        }

        $files = glob($spam_dir . 'antispam_*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $file_time = filemtime($file);
                if (($current_time - $file_time) > $max_age) {
                    unlink($file);
                }
            }
        }
    }
}
