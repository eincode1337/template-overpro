<?php

if(!isset($_SESSION['user_admin'])) get_iframe("404", "Только администратора");

?>
<div class="card install-skins">
    <h2><?= $Function->Translate('_connect_module'); ?></h2>
<form id="save_db">
        <div class="input-form">
            <div class="input_text"><?= $Function->Translate('_host'); ?>:</div>
            <input type="text" name="host" tabindex="1" placeholder="<?= $Function->Translate('_host_info'); ?>" required>
        </div>
        <div class="input-form">
            <div class="input_text"><?= $Function->Translate('_user'); ?>:</div>
            <input type="text" name="user" tabindex="2" placeholder="<?= $Function->Translate('_user_info'); ?>" required>
        </div>
        <div class="input-form">
            <div class="input_text"><?= $Function->Translate('_name_db'); ?>:</div>
            <input type="text" name="name_db" tabindex="3" placeholder="<?= $Function->Translate('_name_db_info'); ?>"required>
        </div>
        <div class="input-form">
            <div class="input_text"><?= $Function->Translate('_pass_db'); ?>:</div>
            <input type="password" name="pass_db" tabindex="4" placeholder="<?= $Function->Translate('_pass_db_info'); ?>" required>
        </div>
        <div class="input-form">
            <div class="input_text"><?= $Function->Translate('_name_server'); ?>:</div>
            <input type="text" name="name_server" tabindex="5" placeholder="<?= $Function->Translate('_name_server_info'); ?>"required>
        </div>
        <p class="color-red text-center"><?= $Function->Translate('_db_info'); ?> <a>/storage/cache/sessions/db.php</a></p>
        <button class="btn w100 btn5px" type="submit"><?= $Function->Translate('_save_db_submit'); ?></button>
    </form>
</div>
