let input, form, stickers, submit;
let savedObjects = [];
let savedButtons = '';
let buttonStates = {};
let currentPatternRangeValue = 0;
let currentFloatValue = 0;
let pagestickers = 1;
let hasChanges = false;
const html_js = $('#html-js');
const loader = $('.loader-skins');
const blockLoader = $('.block-loader');

function setupChoosingServers() {
    const buttons = document.querySelectorAll('.choosing_servers');
    let activeServer = localStorage.getItem('activeServer') || 0;
    let anyActiveServer = false;
    buttons.forEach(button => {
        if (button.id == activeServer) {
            button.classList.add('active');
            anyActiveServer = true;
            localStorage.setItem('activeServer', button.id);
        }
    });
    if (!anyActiveServer && buttons.length > 0) {
        buttons[0].classList.add('active');
        localStorage.setItem('activeServer', buttons[0].id);
    }
    buttons.forEach(button => {
        button.addEventListener('click', function () {
            document.querySelector('.choosing_servers.active')?.classList.remove('active');
            this.classList.add('active');
            localStorage.setItem('activeServer', this.id);
        });
    });
}
setupChoosingServers();    

const idSortAll = localStorage.getItem('idSort') || 'All';
const activeSide = localStorage.getItem('activeSide') || '0';
const activeServer = localStorage.getItem('activeServer') || '0';

$(document).ready(function () {

    

    function SystemForm(formSelect, options) {
        $(formSelect).on("submit", (e) => {
            e.preventDefault();
            const data = $(e.currentTarget).serialize() + "&" + options.param;
            $.ajax({
                type: 'post',
                url: location.href,
                data: data,
                dataType: "json",
                success: function (data) {
                    options.success(data);
                },
                error: function () { console.log(error); }
            });
            return false;
        });
    }
  
    

    function loaderIMG(type) {
        let replaceableImages;
        if (type == 1) {
            replaceableImages = document.querySelectorAll('.loader_img_weapon');
        } else if (type == 2) {
            replaceableImages = document.querySelectorAll('.loader_img_skin');
        } else if (type == 3) {
            replaceableImages = document.querySelectorAll('.loader_img_sticker');
        } else {
            replaceableImages = document.querySelectorAll('.loader_img_all');
        }
        replaceableImages.forEach(function (img) {
            let originalSrc = img.src;
            let parentBlock;
            if (img.closest('.block-skin-img')) {
                parentBlock = img.closest('.block-skin-img');
            } else if (img.closest('.sticker-modal-img')) {
                parentBlock = img.closest('.sticker-modal-img');
            }
            if (parentBlock) {
                parentBlock.classList.add('loader-img');
                img.classList.add('hide_img');
                let loadHandler = function () {
                    parentBlock.classList.remove('loader-img');
                    img.src = originalSrc;
                    img.classList.remove('hide_img');
                    img.classList.add('show_img');
                    img.removeEventListener('load', loadHandler);
                };
                img.addEventListener('load', loadHandler);
            }
        });
    }

    function TippyContent() {
        tippy('[data-tippy-content]', {
            placement: 'right',
            arrow: false,
            animation: 'fade',
            theme: 'overpro',
            allowHTML: true
        });
    }

    function showLoader() {
        loader.show();
        blockLoader.show();
    }

    function hideLoader() {
        loader.hide();
        blockLoader.hide();
    }

    $(document).on('click', '.servers_button', function () {
        const id = $(this).attr('id');
        if (!id) return;
        page_php = id;
        const blocksSort = $('.blocks-sort');
        const activeSide = localStorage.getItem('activeSide');
        const activeServer = localStorage.getItem('activeServer');
        const idSortAll = localStorage.getItem('idSort');
        $('.servers_button').removeClass('server_buttons_active');
        $(this).addClass('server_buttons_active');
        const interfaces = {
            weapons: () => {
                blocksSort.css('display', 'flex');
                interfaceWeapons(idSortAll, activeServer, activeSide, '');
            },
            agents: () => {
                blocksSort.css('display', 'none');
                interfaceAgents(activeServer, activeSide, '');
            },
            coins: () => {
                blocksSort.css('display', 'none');
                interfaceCoins(activeServer, activeSide, '');
            },
            music: () => {
                blocksSort.css('display', 'none');
                interfaceMusic(activeServer, activeSide, '');
            },
            default: () => {
                blocksSort.css('display', 'flex');
                interfaceWeapons(idSortAll, activeServer, activeSide, '');
            }
        };
        interfaces[id] ? interfaces[id]() : interfaces.default();
        const url = `/skins/${id}/`;
        history.pushState({ id }, null, url);
    });

    $(document).on('click', '#sort-skins', function () {
        const activeSide = localStorage.getItem('activeSide');
        const activeServer = localStorage.getItem('activeServer');
        const idSort = $(this).attr('id_sort');
        const url = `/skins/weapons/`;
        history.pushState('weapons', null, url);
        interfaceWeapons(idSort, activeServer, activeSide, '');
        localStorage.setItem('idSort', idSort);
        $('[id="sort-skins"]').removeClass('active').filter(`[id_sort="${idSort}"]`).addClass('active');
        $('.servers_button.server_buttons_active').removeClass('server_buttons_active');
        $('.servers_button[id="weapons"]').addClass('server_buttons_active');
    });
    
    if (!idSortAll || idSortAll === 'All') {
        localStorage.setItem('idSort', 'All');
        $('#sort-skins[id_sort="All"]').addClass('active');
    } else {
        $(`#sort-skins[id_sort="${idSortAll}"]`).addClass('active');
    }

    function setupChoosingSides() {
        const buttons = document.querySelectorAll('.choosing_sides');
        let activeSide = localStorage.getItem('activeSide') || 0;
        let anyActiveSide = false;
        buttons.forEach(button => {
            if (button.id == activeSide) {
                button.classList.add('active');
                anyActiveSide = true;
                localStorage.setItem('activeSide', button.id);
            }
        });
        if (!anyActiveSide && buttons.length > 0) {
            buttons[0].classList.add('active');
            localStorage.setItem('activeSide', buttons[0].id);
        }
        buttons.forEach(button => {
            button.addEventListener('click', function () {
                document.querySelector('.choosing_sides.active')?.classList.remove('active');
                this.classList.add('active');
                localStorage.setItem('activeSide', this.id);
            });
        });
    }
    setupChoosingSides(); 
    
    $('#search_js_skins').on('input', function() {
        let searchText = $(this).val();
        const activeSide = localStorage.getItem('activeSide');
        const activeServer = localStorage.getItem('activeServer');
        const idSortAll = localStorage.getItem('idSort');
        if(page_php == "weapons") { interfaceWeapons(idSortAll, activeServer, activeSide, searchText); }
        if(page_php == "agents") { interfaceAgents(activeServer, activeSide, searchText); }
        if(page_php == "coins") { interfaceCoins(activeServer, activeSide, searchText); }
        if(page_php == "music") { interfaceMusic(activeServer, activeSide, searchText); }
    });

    $(document).on('click', '.choosing_sides', function () {
        const id = $(this).attr('id');
        const activeServer = localStorage.getItem('activeServer');
        const idSortAll = localStorage.getItem('idSort');
        if(page_php == "weapons") { interfaceWeapons(idSortAll, activeServer, id, ''); }
        if(page_php == "agents") { interfaceAgents(activeServer, id, ''); }
        if(page_php == "coins") { interfaceCoins(activeServer, id, ''); }
        if(page_php == "music") { interfaceMusic(activeServer, id, ''); }
    });

    $(document).on('click', '.choosing_servers', function () {
        const id = $(this).attr('id');
        const activeSide = localStorage.getItem('activeSide');
        const idSortAll = localStorage.getItem('idSort');
        if(page_php == "weapons") { interfaceWeapons(idSortAll, id, activeSide, ''); }
        if(page_php == "agents") { interfaceAgents(id, activeSide, ''); }
        if(page_php == "coins") { interfaceCoins(id, activeSide, ''); }
        if(page_php == "music") { interfaceMusic(id, activeSide, ''); }
    });

    

    function interfaceWeapons(idSortAll = 'All', idserver = 0, idteam = 0, searchtext = '') {
        html_js.css('display', 'none');
        showLoader();
        $.ajax({
            type: 'post',
            url: location.href,
            data: { html_weapons: true, id_sort: idSortAll, id_server: idserver, id_team: idteam, search_text: searchtext},
            dataType: 'json',
            global: false,
            success: function (data) {
                hideLoader();
                html_js.html(data.html).css('display', 'grid');
                TippyContent();
                loaderIMG(1);
            },
            error: function (error) {
                console.log(error);
                hideLoader();
                html_js.html('Error JS - 404...');
            }
        });
    }
    if(page_php == "weapons") { interfaceWeapons(idSortAll, activeServer, activeSide, ''); }

    $(document).on('click', '#SkinChangerUpdate', function () {
        let setweapon = $(this);
        if (setweapon.hasClass('choice_active')) return;
        let weaponindex = localStorage.getItem('weapon_index');
        const idskin = setweapon.attr('id_skin');
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer');

        $.ajax({
            type: 'post',
            url: location.href,
            data: { SkinChangerUpdate: true, id_team: idteam, id_server: idserver, weapon_index: weaponindex, id_skin: idskin },
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({
                        content: data.text,
                        type: data.status,
                        time: 5
                    });
                }
                if (data.status === 'success') {
                    $('.block-skin-fon').not(setweapon).removeClass('choice_active');
                    setweapon.addClass('choice_active');
                    const my_skin = $('#my-skin');
                    const activeChoice = $('.choice_active');
                    if (activeChoice.length) {
                        const skinNameElement = activeChoice.find('.skin-name');
                        const skinNameText = skinNameElement.length ? skinNameElement.text() : '-';
                        my_skin.text(skinNameText);
                    } else {
                        my_skin.text('-');
                    }
                    let buttonSkin = setweapon.find('.button-skin');
                    if (!buttonSkin.attr('id')) {
                        buttonSkin.text(choose_default);
                    }
                    hasChanges = true;
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });
    
    $(document).on('click', '#SkinChangerSettingModal', function () {
        let weaponindex = localStorage.getItem('weapon_index');
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer'); 
        savedObjects = $('#skin-modal-js').children().toArray();
        savedButtons = $('.cnopick').html();
        $('#skin-modal-js').html('<div class="block-loader-modal"><div class="loader-skins-modal"></div></div>')
        $.ajax({
            type: 'post',
            url: location.href,
            data: { SkinChangerSettingModal: true, id_team: idteam, id_server: idserver, weapon_index: weaponindex }, 
            dataType: 'json',
            global: false,
            success: function (data) { 
                $('#skin-modal-js').html(data.html);
                $('.cnopick').html('<a class="modal_deff" id="SkinChangerSkins">' + selectel_skins + '</a>');
                renderPagination(data.count_stickers, 12, 1);
                loaderIMG(3);
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    $(document).on('click', '#SkinChangerSetting', function () {
        let weaponindex = localStorage.getItem('weapon_index');
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer');
        const countstattrak = $('#SkinChangerSettingForm input[name="stattrack_count"]').val();
        const float = $('#SkinChangerSettingForm input[name="float"]').val();
        const pattern = $('#SkinChangerSettingForm input[name="pattern"]').val();
        const nametag = $('#SkinChangerSettingForm input[name="nametag"]').val();
        let stattrack = 0;

        if ($('.sk-modal-StatTrak-button').hasClass('off')) {
            stattrack = "0";
        } else {
            stattrack = "1";
        }

        $.ajax({
            type: 'post',
            url: location.href,
            data: { SkinChangerSetting: true, id_team: idteam, id_server: idserver, weapon_index: weaponindex, stattrack: stattrack, stattrack_count: countstattrak, float: float, pattern: pattern, nametag: nametag }, 
            dataType: 'json',
            global: false,
            success: function (data) { 
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    hasChanges = true;
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });
    
    $(document).on('click', '#SkinChangerSkins', function () {
        $('#skin-modal-js').html(savedObjects);
        $('.cnopick').html(savedButtons);
    });    

    $(document).on('click', '#SkinChangerAddKnife', function (e) {
        if ($(e.target).closest('#set-skin').length) return;
        let setweapon = $(this);
        if (setweapon.hasClass('choice_active')) return;
        const id_knife = $(this).attr('id_knife');
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer'); 
        $.ajax({
            type: 'post',
            url: location.href,
            data: { SkinChangerAddKnife: true, id_team: idteam, id_server: idserver, id_knife: id_knife }, 
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    $('.block-skin-fon#SkinChangerAddKnife').not(setweapon).removeClass('choice_active');
                    setweapon.toggleClass('choice_active');
                    let buttonSkin = setweapon.find('.button-skin');
                    if (!buttonSkin.attr('id')) buttonSkin.text(choose_default);
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    $(document).on('click', '#SkinChangerAddGlove', function (e) { 
        if ($(e.target).closest('#set-skin').length) return;
        let setweapon = $(this);
        if (setweapon.hasClass('choice_active')) return;
        const id_glove = $(this).attr('id_glove');
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer'); 
        $.ajax({
            type: 'post',
            url: location.href,
            data: { SkinChangerAddGlove: true, id_team: idteam, id_server: idserver, id_glove: id_glove }, 
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    $('.block-skin-fon#SkinChangerAddGlove').not(setweapon).removeClass('choice_active');
                    setweapon.toggleClass('choice_active');
                    let buttonSkin = setweapon.find('.button-skin');
                    if (!buttonSkin.attr('id')) buttonSkin.text(choose_default);
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    $(document).on('click', '#SkinChangerNoSkin', function () {
        const weaponindex = $(this).attr('weapon_index');
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer');

        $.ajax({
            type: 'post',
            url: location.href,
            data: { SkinChangerNoSkin: true, id_team: idteam, id_server: idserver, weapon_index: weaponindex }, 
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    $('#my-skin').text('-');
                    $('.skin-modal-js').find('.block-skin-fon').removeClass('choice_active');
                    let buttonsSkin = $('.block-skin-fon').find('.button-skin');
                    buttonsSkin.each(function() {
                        let buttonSkin = $(this);
                        if (!buttonSkin.attr('id')) {
                            buttonSkin.text(choose_weapon);
                        }
                    });
                    hasChanges = true;
                }   
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    

    function interfaceAgents(idserver = 0, idteam = 0, searchtext = '') {
        html_js.css('display', 'none');
        showLoader();
        $.ajax({
            type: 'post',
            url: location.href,
            data: { html_agents: true, id_server: idserver, id_team: idteam, search_text: searchtext},
            dataType: 'json',
            global: false,
            success: function (data) {
                hideLoader();
                html_js.html(data.html).css('display', 'grid');
                loaderIMG(0);
            },
            error: function (error) {
                console.log(error);
                hideLoader();
                html_js.html('Error JS - 404...');
            }
        });
    }
    if(page_php == "agents") { interfaceAgents(activeServer, activeSide, ''); }

    $(document).on('click', '#set_agent', function () {
        let setagent = $(this);
        const idagent = $(this).attr('id_agent');
        const hasChoiceActiveClass = $(this).hasClass('choice_active');
        const typeagent = hasChoiceActiveClass ? 1 : 0;
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer');
        $.ajax({
            type: 'post',
            url: location.href,
            data: { set_agent: true, id_agent: idagent, type_agent: typeagent, id_team: idteam, id_server: idserver},
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    $('.block-skin-fon').not(setagent).removeClass('choice_active');
                    setagent.toggleClass('choice_active');
                    let buttonSkin = setagent.find('.button-skin');
                    if (setagent.hasClass('choice_active')) {
                        buttonSkin.text(choose_default);
                    } else {
                        buttonSkin.text(choose_weapon);
                    }
                }                
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    

    function interfaceCoins(idserver = 0, idteam = 0, searchtext = '') {
        html_js.css('display', 'none');
        showLoader();
        $.ajax({
            type: 'post',
            url: location.href,
            data: { html_coins: true, id_server: idserver, id_team: idteam, search_text: searchtext},
            dataType: 'json',
            global: false,
            success: function (data) {
                hideLoader();
                html_js.html(data.html).css('display', 'grid');
                loaderIMG(0);
            },
            error: function (error) {
                console.log(error);
                hideLoader();
                html_js.html('Error JS - 404...');
            }
        });
    }
    if(page_php == "coins") { interfaceCoins(activeServer, activeSide, ''); }

    $(document).on('click', '#set_coins', function () {
        let setcoins = $(this);
        const idcoins = $(this).attr('id_coins');
        const hasChoiceActiveClass = $(this).hasClass('choice_active');
        const typecoins = hasChoiceActiveClass ? 1 : 0;
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer');
        $.ajax({
            type: 'post',
            url: location.href,
            data: { set_coins: true, id_coins: idcoins, type_coins: typecoins, id_team: idteam, id_server: idserver},
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    $('.block-skin-fon').not(setcoins).removeClass('choice_active');
                    setcoins.toggleClass('choice_active');
                    let buttonSkin = setcoins.find('.button-skin');
                    if (setcoins.hasClass('choice_active')) {
                        buttonSkin.text(choose_default);
                    } else {
                        buttonSkin.text(choose_weapon);
                    }
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });  

    

    function interfaceMusic(idserver = 0, idteam = 0, searchtext = '') {
        html_js.css('display', 'none');
        showLoader();
        $.ajax({
            type: 'post',
            url: location.href,
            data: { html_music: true, id_server: idserver, id_team: idteam, search_text: searchtext},
            dataType: 'json',
            global: false,
            success: function (data) {
                hideLoader();
                html_js.html(data.html).css('display', 'grid');
                loaderIMG(0);
            },
            error: function (error) {
                console.log(error);
                hideLoader();
                html_js.html('Error JS - 404...');
            }
        });
    }
    if(page_php == "music") { interfaceMusic(activeServer, activeSide, ''); }

    $(document).on('click', '#set_music', function () { 
        let setmusic = $(this);
        const idmusic = $(this).attr('id_music');
        const hasChoiceActiveClass = $(this).hasClass('choice_active');
        const typemusic = hasChoiceActiveClass ? 1 : 0;
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer');
        $.ajax({
            type: 'post',
            url: location.href,
            data: { set_music: true, id_music: idmusic, type_music: typemusic, id_team: idteam, id_server: idserver},
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    $('.block-skin-fon').not(setmusic).removeClass('choice_active');
                    setmusic.toggleClass('choice_active');
                    let buttonSkin = setmusic.find('.button-skin');
                    if (setmusic.hasClass('choice_active')) {
                        buttonSkin.text(choose_default);
                    } else {
                        buttonSkin.text(choose_weapon);
                    }
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    

    $(document).on('click', '#server_delete', function () {
        const idserver = $(this).attr('id_server');
        const button = $(this);
        $.ajax({
            type: 'post',
            url: location.href,
            data: { server_delete: true, id_server: idserver},
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    button.closest('.block-server').remove();
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });    

    SystemForm("#save_settings_sk", {
        param: "save_settings_sk",
        success: function(data) {
            if (typeof noty === "function") {
                if (data.status == "success") {
                    noty(data.text, data.status, domain + '/storage/assets/sounds/success2.mp3', 0.2)
                } else {
                    noty(data.text, data.status, domain + '/storage/assets/sounds/error.mp3', 0.2)
                }
            } else {
                note({ content: data.text, type: data.status, time: 5 });
            }
            if (data.status == "success") setTimeout(function () { location.reload(); }, 3000);
        },
    });

    SystemForm("#add_servers_sk", {
        param: "add_servers_sk",
        success: function(data) {
            if (typeof noty === "function") {
                if (data.status == "success") {
                    noty(data.text, data.status, domain + '/storage/assets/sounds/success2.mp3', 0.2)
                } else {
                    noty(data.text, data.status, domain + '/storage/assets/sounds/error.mp3', 0.2)
                }
            } else {
                note({ content: data.text, type: data.status, time: 5 });
            }
            if (data.status == "success") setTimeout(function () { location.reload(); }, 3000);
        },
    });

    SystemForm("#save_db", {
        param: "save_db",
        success: function(data) {
            if (typeof noty === "function") {
                if (data.status == "success") {
                    noty(data.text, data.status, domain + '/storage/assets/sounds/success2.mp3', 0.2)
                } else {
                    noty(data.text, data.status, domain + '/storage/assets/sounds/error.mp3', 0.2)
                }
            } else {
                note({ content: data.text, type: data.status, time: 5 });
            }
            if (data.status == "success") setTimeout(function () { location.reload(); }, 3000);
        },
    });

    function disableButtons() {
        $('.UpdateCache').find('.adminpanel-cache-button').prop('disabled', true);
    }

    function enableButtons() {
        $('.UpdateCache').find('.adminpanel-cache-button').prop('disabled', false);
    }

    function sendAjaxRequest(action, updateCacheButton) {

        let buttonId = updateCacheButton.attr('id');
        buttonStates[buttonId] = updateCacheButton.prop('outerHTML');

        disableButtons();

        updateCacheButton.replaceWith('<div class="loader-button" id="' + buttonId + '"></div>');

        $.ajax({
            type: 'post',
            url: location.href,
            data: { [action]: true },
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    $('#'+buttonId+'.loader-button').replaceWith(buttonStates[buttonId]);
                }
            },
            error: function (error) {
                console.log(error);
                $('#'+buttonId+'.loader-button').replaceWith(buttonStates[buttonId]);
            },
            complete: function () {
                enableButtons();
            }
        });
    }

    $(document).on('click', '.adminpanel-cache-button', function () {
        let updateCacheButton = $(this);
        let action = updateCacheButton.attr('id');
        sendAjaxRequest(action, updateCacheButton);
    });

    $(document).on('click', '#set-skin', function () {
        const idName = $(this).attr('id_name');
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer');
        modalSkins(idName, idteam, idserver);
        $('.skin-modal, .skin-modal-overlay').addClass('active-modal');
    });

    $(document).on('click', '.sk-modal-weapon-sticker', function () {
        $('.sk-modal-weapon-sticker').removeClass('active');
        const id = $(this).attr('id');
        $.ajax({
            type: 'post',
            url: location.href,
            data: { StickersKeychainHtml: true, id: id },
            dataType: 'json',
            global: false,
            success: function (data) {
                const container = $('.sk-modal-settings-type-2');
                const lastBlock = container.find('.sk-modal-block').last();
                lastBlock.html(data.html);
                renderPagination(data.count, 12, 1);
                loaderIMG(3);
            },
            error: function (error) {
                console.log(error);
            }
        });
        $(this).addClass('active');
    });

    $(document).on('click', '.sk-modal-StatTrak-button', function () {
        let button = $(this);
        if (button.hasClass('off')) {
            button.removeClass('off').addClass('on').text('StatTrak ON');
        } else {
            button.removeClass('on').addClass('off').text('StatTrak OFF');
        }
    });

    $(document).on('click', '.sk-modal-setting-button', function () {
        var buttonValue = parseFloat($(this).data('value'));
        updateInputs(buttonValue, currentPatternRangeValue);
    });

    $(document).on('input', 'input[name="float-range"]', function () {
        let floatValue = parseFloat($(this).val());
        $('input[name="float"]').val(floatValue);
        updateActiveButton(floatValue);
    });

    $(document).on('change', 'input[name="float"]', function () {
        currentFloatValue = parseFloat($(this).val());
        $('input[name="float-range"]').val(currentFloatValue);
        updateActiveButton(currentFloatValue);
    });

    $(document).on('input', 'input[name="pattern-range"]', function () {
        currentPatternRangeValue = parseFloat($(this).val());
        $('input[name="pattern"]').val(currentPatternRangeValue);
    });

    function updateInputs(floatValue, patternRangeValue) {
        $('input[name="float"]').val(floatValue);
        $('input[name="float-range"]').val(floatValue);
        $('input[name="pattern"]').val(patternRangeValue);
        $('input[name="pattern-range"]').val(patternRangeValue);
        updateActiveButton(floatValue);
    }

    function updateActiveButton(floatValue) {
        $('.sk-modal-setting-button').removeClass('active');
        let closestButton = findClosestButton(floatValue);
        closestButton.addClass('active');
    }

    function findClosestButton(floatValue) {
        let buttons = $('.sk-modal-setting-button');
        let minDifference = Infinity;
        let closestButton;
        buttons.each(function () {
            let buttonValue = parseFloat($(this).data('value'));
            let difference = Math.abs(floatValue - buttonValue);
            if (difference < minDifference) {
                minDifference = difference;
                closestButton = $(this);
            }
        });
        return closestButton;
    }  
     
    $(document).on('click', '.pagination-btn', function () {
        const pageNumber = $(this).data('page');
        if (pageNumber) {
            pagestickers = pageNumber;
            loadStickers($('#search_js_stickers').val(), pagestickers);
        }
    });

    $(document).on('input', '#search_js_stickers', function () {
        pagestickers = 1;
        loadStickers($(this).val(), pagestickers);
    });

    function loadStickers(textstickers, pagestickers) {
        const id = $('.sk-modal-weapon-sticker.active').attr('id');
        $.ajax({
            type: 'post',
            url: location.href,
            data: { SkinChangerStickers: true, text_stickers: textstickers, page_stickers: pagestickers, id: id },
            dataType: 'json',
            global: false,
            success: function (data) {
                const container = $('.stickers-modal-js');
                container.html(data.html);
                renderPagination(data.count_stickers, 12, pagestickers);
                loaderIMG(3);
            },
            error: function (error) {
                console.log(error);
            }
        });
    }

    function renderPagination(totalStickers, stickersPerPage, currentPage) {
        const totalPages = Math.ceil(totalStickers / stickersPerPage);
        const paginationContainer = $('#stickers-pagination');
        paginationContainer.empty(); 
        if (totalPages > 1) {
            const maxPagesToShow = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
            let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
            if (endPage - startPage + 1 < maxPagesToShow) {
                startPage = Math.max(1, endPage - maxPagesToShow + 1);
            }
            if (startPage > 1) {
                paginationContainer.append('<button class="pagination-btn" data-page="1">«</button>');
            }
            if (currentPage > 1) {
                paginationContainer.append('<button class="pagination-btn" data-page="' + (currentPage - 1) + '">‹</button>');
            }
            for (let i = startPage; i <= endPage; i++) {
                paginationContainer.append('<button class="pagination-btn' + (i === currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>');
            }
            if (endPage < totalPages) {
                paginationContainer.append('<button class="pagination-btn" data-page="' + (currentPage + 1) + '">›</button>');
            }
            if (currentPage < totalPages) {
                paginationContainer.append('<button class="pagination-btn" data-page="' + totalPages + '">»</button>');
            }
        }
    }    
    
    $(document).on('click', '#StickerUpdate', function () {
        let setsticker = $(this);
        let savedsticker = setsticker.find('.sticker-modal-img').html();
        let weaponindex = localStorage.getItem('weapon_index');
        const idteam = localStorage.getItem('activeSide');
        const idserver = localStorage.getItem('activeServer');
        const idslot = $('.sk-modal-weapon-sticker.active').attr('id');
        const idsticker = $(this).attr('id_sticker');
        $.ajax({
            type: 'post',
            url: location.href,
            data: { StickerUpdate: true, id_server: idserver, id_team: idteam, id_sticker: idsticker, weapon_index: weaponindex, id_slot: idslot },
            dataType: 'json',
            global: false,
            success: function (data) {
                if (typeof noty === 'function') {
                    noty(data.text, data.status, '/storage/assets/sounds/success2.mp3', 0.2);
                } else {
                    note({ content: data.text, type: data.status, time: 5 });
                }
                if (data.status === 'success') {
                    if(idsticker == 0) {
                        if (idslot > 4) {
                            $('.sk-modal-weapon-sticker.active').html('<svg class="plus-slot" viewBox="0 0 448 512"><path d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32V224H48c-17.7 0-32 14.3-32 32s14.3 32 32 32H192V432c0 17.7 14.3 32 32 32s32-14.3 32-32V288H400c17.7 0 32-14.3 32-32s-14.3-32-32-32H256V80z"></path></svg><svg class="img-slot" id="Capa_1" x="0px" y="0px" viewBox="0 0 512.001 512.001" xml:space="preserve"><g><g><path d="M321.645,34.956c-3.883-3.927-10.213-3.964-14.142-0.081l-0.297,0.296c-3.905,3.905-3.905,10.237,0,14.143 c1.952,1.953,4.511,2.929,7.071,2.929c2.559,0,5.119-0.976,7.071-2.929l0.216-0.216C325.491,45.215,325.527,38.884,321.645,34.956 z"></path></g></g><g><g><path d="M476.873,35.172c-36.359-36.358-91.758-45.528-137.857-22.818c-4.954,2.441-6.992,8.436-4.551,13.39 c2.441,4.954,8.436,6.989,13.39,4.551c38.411-18.923,84.577-11.28,114.877,19.021c38.979,38.979,38.979,102.404,0,141.383 c-36.592,36.593-94.728,38.831-133.959,6.719l14.241-14.241c14.371,11.169,31.692,16.776,49.027,16.776 c20.482,0,40.963-7.796,56.557-23.389c31.185-31.185,31.185-81.927,0-113.113c-31.186-31.185-81.927-31.185-113.113,0 c-28.789,28.789-30.978,74.233-6.613,105.584l-14.231,14.231c-25.086-30.596-29.936-73.166-11.563-108.933 c2.523-4.913,0.587-10.94-4.326-13.464c-4.913-2.523-10.94-0.587-13.464,4.326c-19.335,37.638-17.252,81.556,3.846,116.557 c-5.816,1.432-11.325,4.413-15.86,8.948l-10.131,10.131l-45.514-45.514c-8.686-8.686-20.234-13.469-32.517-13.469 s-23.831,4.784-32.517,13.469L13.492,294.418c-17.929,17.93-17.929,47.103,0,65.034l139.103,139.103 c8.965,8.965,20.741,13.447,32.516,13.447s23.552-4.482,32.517-13.447l38.292-38.293c3.906-3.905,3.906-10.237,0-14.143 c-3.904-3.905-10.237-3.905-14.142,0l-38.292,38.293c-10.132,10.132-26.617,10.133-36.749,0L27.634,345.31 c-10.132-10.132-10.132-26.618,0-36.749l139.103-139.104c4.908-4.908,11.434-7.611,18.375-7.611s13.467,2.703,18.375,7.611 l139.103,139.103c4.908,4.908,7.611,11.434,7.611,18.375s-2.703,13.467-7.611,18.375l-50.145,50.144 c-3.906,3.905-3.906,10.237,0,14.143c3.906,3.905,10.238,3.905,14.142,0l50.145-50.144c17.93-17.93,17.93-47.104,0-65.034 l-45.514-45.514l10.131-10.131c4.543-4.543,7.528-10.063,8.956-15.89c18.926,11.355,40.329,17.039,61.736,17.039 c30.722,0,61.445-11.694,84.833-35.082C523.65,158.063,523.65,81.949,476.873,35.172z M349.626,77.592 c11.329-11.33,26.392-17.569,42.414-17.569s31.085,6.24,42.414,17.569c11.33,11.329,17.569,26.392,17.569,42.414 s-6.24,31.085-17.569,42.414c-11.329,11.33-26.392,17.569-42.414,17.569s-31.085-6.24-42.414-17.569 c-11.33-11.329-17.569-26.392-17.569-42.414C332.057,103.985,338.296,88.921,349.626,77.592z M307.206,224.63l-10.131,10.131 l-19.791-19.791l10.131-10.131c5.455-5.457,14.334-5.458,19.791,0C312.663,210.297,312.663,219.174,307.206,224.63z"></path></g></g><g><g><path d="M242.226,269.819c-31.493-31.491-82.735-31.492-114.229,0c-31.492,31.493-31.492,82.735,0,114.229 c15.747,15.747,36.43,23.62,57.114,23.62c20.685,0,41.368-7.873,57.115-23.62C273.718,352.555,273.718,301.313,242.226,269.819z M228.083,369.906c-11.479,11.479-26.739,17.8-42.972,17.8s-31.494-6.321-42.972-17.8c-11.479-11.479-17.8-26.739-17.8-42.972 c0-16.233,6.321-31.493,17.8-42.972c11.848-11.848,27.409-17.772,42.972-17.772s31.124,5.924,42.972,17.772 C251.779,307.656,251.779,346.212,228.083,369.906z"></path></g></g><g><g><path d="M281.916,420.124c-1.859-1.87-4.439-2.93-7.07-2.93c-2.63,0-5.21,1.06-7.07,2.93c-1.86,1.86-2.93,4.43-2.93,7.07 c0,2.63,1.07,5.21,2.93,7.07s4.44,2.93,7.07,2.93s5.21-1.07,7.07-2.93c1.87-1.86,2.93-4.44,2.93-7.07 C284.846,424.554,283.786,421.984,281.916,420.124z"></path></g></g></svg>');
                        } else {
                            $('.sk-modal-weapon-sticker.active').html('<svg class="plus-slot" viewBox="0 0 448 512"><path d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32V224H48c-17.7 0-32 14.3-32 32s14.3 32 32 32H192V432c0 17.7 14.3 32 32 32s32-14.3 32-32V288H400c17.7 0 32-14.3 32-32s-14.3-32-32-32H256V80z"></path></svg><svg class="img-slot" viewBox="0 -10 512.00005 512"><path d="m436.960938 75.007812c-85.984376-85.984374-216.566407-97.453124-314.546876-37.441406-3.535156 2.167969-4.648437 6.789063-2.480468 10.324219 2.164062 3.539063 6.789062 4.648437 10.324218 2.480469 37.734376-23.113282 81.195313-35.328125 125.683594-35.328125 132.878906 0 240.984375 108.105469 240.984375 240.980469 0 50.964843-15.589843 99.367187-45.140625 140.433593-24.300781-14.980469-51.148437-24.828125-79.167968-29.070312 1.1875-7.277344.140624-4.433594-14.433594-92.210938-.574219-3.234375.515625-6.664062 2.933594-9.019531l60.910156-59.378906c14.847656-14.457032 6.90625-39.96875-13.960938-43.003906l-84.195312-12.234376c-3.324219-.480468-6.1875-2.5625-7.667969-5.574218l-37.660156-76.285156c-9.238281-18.738282-35.972657-18.726563-45.203125 0l-37.660156 76.285156c-1.480469 3.011718-4.34375 5.09375-7.667969 5.574218l-84.191407 12.234376c-20.855468 3.035156-28.820312 28.539062-13.964843 43.003906l19.027343 18.546875c2.964844 2.894531 7.71875 2.832031 10.613282-.128907 2.890625-2.972656 2.832031-7.730468-.132813-10.621093l-19.027343-18.550781c-6.125-5.976563-2.636719-16.1875 5.644531-17.386719l84.183593-12.230469c8.21875-1.191406 15.316407-6.359375 18.992188-13.796875l37.644531-76.285156c3.734375-7.59375 14.546875-7.59375 18.28125 0l37.644531 76.285156c3.675782 7.4375 10.773438 12.605469 18.992188 13.796875l84.183594 12.230469c8.265625 1.195312 11.777344 11.402343 5.644531 17.386719l-60.910156 59.382812c-4.84375 4.714844-7.617188 11.289062-7.617188 18.046875 0 8.324219 15.824219 87.273437 14.644531 92.203125-9.269531-.753906-18.734374-.902344-28.109374-.390625l-61.882813-32.542969c-7.335937-3.851562-16.113281-3.859375-23.460937 0l-75.296876 39.589844c-7.09375 3.707031-14.957031-1.726562-14.957031-8.898438 0-3.949218 14.90625-84.363281 14.90625-89.960937 0-6.757813-2.773437-13.332031-7.605469-18.046875l-20.390624-19.871094c-2.972657-2.894531-7.730469-2.832031-10.621094.128906-2.894532 2.972657-2.832032 7.726563.140625 10.621094l20.390625 19.871094c2.375 2.324219 3.5 5.722656 2.921875 9.027344-14.804688 87.0625-14.753907 84.238281-14.753907 88.230468 0 18.65625 19.929688 31.09375 36.957032 22.191407l75.292968-39.589844c2.925782-1.53125 6.566407-1.53125 9.492188 0l42.421875 22.300781c-21.851563 3.925782-43.042969 11.253906-62.59375 21.753906-3.652344 1.960938-5.023437 6.507813-3.0625 10.160157 1.960937 3.652343 6.507813 5.023437 10.160156 3.0625 56.734375-30.441407 127.332031-31.886719 186.925781.507812-66.542968 17.527344-196.628906 51.796875-261.308593 68.890625 13.191406-20.832031 30.238281-39.25 49.988281-53.902344 3.324219-2.472656 4.023438-7.179687 1.550781-10.5-2.472656-3.335937-7.175781-4.027343-10.5-1.5625-24.347656 18.070313-44.871093 41.472657-59.699219 67.984376-162.230468-75.238282-189.214843-295.917969-48.164062-407.675782 3.253906-2.574218 3.800781-7.296875 1.226562-10.546875-2.578124-3.253906-7.300781-3.800781-10.546874-1.222656-151.703126 120.195313-120.484376 359.375 57.808593 436.035156 3.984375 1.726563-14.550781 5.519531 300.554688-77.480469 1.628906-.398437 3.300781-1.578124 4.191406-2.738281 77.03125-100.992187 68.730469-244.664062-22.679687-336.074219zm0 0"></path></svg>');
                        }
                    } else {
                        $('.sk-modal-weapon-sticker.active').html(savedsticker);
                    }
                    hasChanges = true;
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });       

    function modalSkins(idname, idteam, idserver) {
        hasChanges = false;
        $.ajax({
            type: 'post',
            url: location.href,
            data: { id_name: idname, id_team: idteam, id_server: idserver },
            dataType: 'json',
            global: false,
            success: function (data) {
                $('#skin-modal-js').html(data.html);
                localStorage.setItem('weapon_index', data.id);
                const buttons = $('.cnopick');
                buttons.html(data.buttons);
                const my_skin = $('#my-skin');
                const activeChoice = $('.choice_active');
                if (activeChoice.length) {
                    const skinNameElement = activeChoice.find('.skin-name');
                    const skinNameText = skinNameElement.length ? skinNameElement.text() : '-';
                    my_skin.text(skinNameText);
                } else {
                    my_skin.text('-');
                }
                TippyContent();
                loaderIMG(2);
            },
            error: function (error) {
                console.log(error);
            }
        });
    }

    $('.skin-modal-overlay, .modal-btn__close').click(function () { closeSkinModal(); });
    $('.servers-modal-overlay, .servers-modal .choosing_servers').click(function () { closeSkinModal(); setupChoosingServers(); });
    $(document).keyup(function (e) { if (e.key === 'Escape') { closeSkinModal(); } });
    function closeSkinModal() { 
        $('.skin-modal, .skin-modal-overlay').removeClass('active-modal'); 
        $('.servers-modal, .servers-modal-overlay').addClass('no-active-modal'); 
        $('#SkinChangerSetting').remove(); 
        $('#SkinChangerNoSkin').remove(); 
        $('#skin-modal-js').html('<div class="block-loader-modal"><div class="loader-skins-modal"></div></div>');
        
        if (hasChanges && page_php == "weapons") {
            const idteam = localStorage.getItem('activeSide');
            const idserver = localStorage.getItem('activeServer');
            const idSortAll = localStorage.getItem('idSort');
            interfaceWeapons(idSortAll, idserver, idteam, '');
        }
        
        hasChanges = false;
    }

});