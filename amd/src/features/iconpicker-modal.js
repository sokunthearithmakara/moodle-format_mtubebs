/* eslint-disable */
/* !
* IconPicker ('https://github.com/furcan/IconPicker')
* Version: 1.5.0
* Author: Furkan MT ('https://github.com/furcan')
* Dependencies: Font Awesome Free v5.11.2 (https://fontawesome.com/license/free)
* Copyright 2019 IconPicker, MIT Licence ('https://opensource.org/licenses/MIT')*
*/

'use strict';
import ModalFactory from 'core/modal';
import ModalEvents from 'core/modal_events';
import {getString} from 'core/str';
import $ from 'jquery';

// IconPicker: Default Options on
var ipDefaultOptions = {
    jsonUrl: null,
    searchPlaceholder: '',
    showAllButton: '',
    cancelButton: '',
    noResultsFound: '',
    borderRadius: '20px',
};
var ipNewOptions;
// IconPicker: Default Options off

// IconPicker: Extend Options on
var extendIconPicker = function() {
    var extended = {};
    var deep = false;
    var i = 0;
    if (Object.prototype.toString.call(arguments[0]) === '[object Boolean]') {
        deep = arguments[0];
        i++;
    }
    var merge = function(obj) {
        for (var prop in obj) {
            if (obj.hasOwnProperty(prop)) {
                if (deep && Object.prototype.toString.call(obj[prop]) === '[object Object]') {
                    extended[prop] = extendIconPicker(extended[prop], obj[prop]);
                } else {
                    extended[prop] = obj[prop];
                }
            }
        }
    };
    for (; i < arguments.length; i++) {
        merge(arguments[i]);
    }
    return extended;
};
// IconPicker: Extend Options off


// IconPicker: Main on
var IconPicker = {

    // Init
    Init: async function(ipUserOptions) {
        const localized = {
            searchPlaceholder: await getString('iconpickersearch', 'format_mtubebs'),
            showAllButton: await getString('iconpickershowall', 'format_mtubebs'),
            cancelButton: await getString('cancel', 'moodle'),
            noResultsFound: await getString('iconpickernoresults', 'format_mtubebs'),
        };
        ipNewOptions = extendIconPicker(true, ipDefaultOptions, localized, ipUserOptions);
    },

    // Run
    Run: async function(theButton, theCallback) {
        let jsonUrl = ipNewOptions.jsonUrl;
        let showAllButton = ipNewOptions.showAllButton;
        let cancelButton = ipNewOptions.cancelButton;
        let searchPlaceholder = ipNewOptions.searchPlaceholder;
        let borderRadius = ipNewOptions.borderRadius;
        let inputElement = ipNewOptions.inputElement;
        let previewElement = ipNewOptions.previewElement;

        // IconPicker: Get Library from JSON and AppendTo Body on
        const getIconListXmlHttpRequest = function(jsonUrl, buttonShowAll, buttonCancel, searchPlaceholder, borderRadius, inputElement, previewElement, theCallback, target) {

            // If chrome browser
            if (navigator.userAgent.toLowerCase().indexOf('chrome') > -1) {
                // Check the protocol
                if (window.location.protocol.indexOf('http') <= -1) {
                    ipConsoleLog('Chrome Browser blocked this request by CORS policy.');
                    return false;
                }
            }

            // Modal element
            var ipElement = document.getElementById('IconPickerModal');

            // If modal element doesn't exist on document send XMLHttpRequest
            if (!ipElement) {
                var xmlHttp = new XMLHttpRequest();
                xmlHttp.open('GET', jsonUrl, true);
                xmlHttp.setRequestHeader('Content-type', 'application/json; charset=utf-8');
                xmlHttp.send();
                xmlHttp.onreadystatechange = function() {
                    if (this.readyState === 4) {
                        if (this.status === 200) { // Success
                            var data = this.responseText;
                            appendIconListToBody(data, buttonShowAll, buttonCancel, searchPlaceholder, borderRadius, inputElement, previewElement, theCallback, target);
                        } else {
                            ipConsoleError('XMLHttpRequest Failed.');
                        }
                    }
                };
            }
        };
        // IconPicker: Get Library from JSON and AppendTo Body off


        // IconPicker: Append Library to Body on
        var appendIconListToBody = async function(data, buttonShowAll, buttonCancel, searchPlaceholder, borderRadius, inputElement, previewElement, theCallback, target) {

            // Data
            var jsonData = JSON.parse(data);

            // Icons
            var icons = '';
            for (var key in jsonData) {
                if (jsonData.hasOwnProperty(key)) {
                    var forObj = jsonData[key];
                    var icon = '<i data-search="' + forObj + '" data-class="' + forObj + '" class="first-icon select-icon ' + forObj + '"></i>';
                    icons += icon;
                }
            }

            // Loading indicator
            var loadingIndicator = '<div id="IconPickerLoading"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="60" height="60" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid"><g transform="translate(25 50)"><circle cx="0" cy="0" r="9" fill="#1e1e1e" transform="scale(0.24 0.24)"><animateTransform attributeName="transform" type="scale" begin="-0.2666s" calcMode="spline" keySplines="0.3 0 0.7 1;0.3 0 0.7 1" values="0;1;0" keyTimes="0;0.5;1" dur="0.8s" repeatCount="indefinite"/></circle></g><g transform="translate(50 50)"><circle cx="0" cy="0" r="9" fill="#1e1e1e" transform="scale(0.00153 0.00153)"><animateTransform attributeName="transform" type="scale" begin="-0.1333s" calcMode="spline" keySplines="0.3 0 0.7 1;0.3 0 0.7 1" values="0;1;0" keyTimes="0;0.5;1" dur="0.8s" repeatCount="indefinite"/></circle></g><g transform="translate(75 50)"><circle cx="0" cy="0" r="9" fill="#1e1e1e" transform="scale(0.3 0.3)"><animateTransform attributeName="transform" type="scale" begin="0s" calcMode="spline" keySplines="0.3 0 0.7 1;0.3 0 0.7 1" values="0;1;0" keyTimes="0;0.5;1" dur="0.8s" repeatCount="indefinite"/></circle></g></svg></div>';

            // Icons wrap
            var iconWrap = '<div class="ip-icons-content" style="border-radius:' + borderRadius + ';">' +
                '<div class="ip-icons-search"><input id="IconPickerSearch" type="text" spellcheck="false" autocomplete="off" placeholder="' + searchPlaceholder + '" style="border-radius:' + borderRadius + ';" /><i class="placeholder-icon fas fa-search"></i></div>' +
                '<div class="ip-icons-search-results"></div>' +
                '<div class="ip-icons-area">' +
                loadingIndicator +
                icons +
                '<a class="ip-show-all-icons btn" style="border-radius:' + borderRadius + ';">' + buttonShowAll + '</a>' +
                '</div>' +
                '<div class="ip-icons-footer"><a class="btn cancel" style="border-radius:' + borderRadius + ';">' + buttonCancel + '</a></div>' +
                '</div>';

            var IconPickerModal = document.createElement('div');
            IconPickerModal.id = 'IconPickerModal';
            IconPickerModal.innerHTML = iconWrap;

            // Body
            // Create the modal element
            let IconPickerM = await ModalFactory.create({
                body: IconPickerModal,
                large: true,
                show: false,
                removeOnClose: true,
                isVerticallyCentered: true,
            });

            let root = IconPickerM.getRoot();
            root.find('.modal-header').remove();
            root.find('.modal-dialog').toggleClass('modal-lg modal-xl');
            root.find('.modal-footer').remove();
            root.find('.modal-body').addClass('p-0');

            root.on(ModalEvents.hidden, function() {
                // Remove the icon picker from the DOM.
                IconPickerM.destroy();
            });

            IconPickerM.show();

            root.on(ModalEvents.shown, function() {
                // Get the modal element
                var ipElement = document.getElementById('IconPickerModal');

                // Modal element display css
                ipElement.style.display = 'block';

                // Heights
                var ipHeight = parseInt(ipElement.offsetHeight);
                var winHeight = parseInt(window.innerHeight);

                // Modal element position top css on
                var liveScrollTop = parseInt(window.pageYOffset || document.documentElement.scrollTop);
                var totalTopPos = liveScrollTop + ((winHeight - ipHeight) / 2);
                if (winHeight + 20 <= ipHeight) {
                    totalTopPos = liveScrollTop;
                }
                ipElement.style.top = totalTopPos + 'px';
                // Modal element position top css off

                // Remove loading indicator on
                var loadingElement = document.getElementById('IconPickerLoading');
                var ltAnimate = setTimeout(function() {
                    loadingElement.classList.add('hide');
                    clearTimeout(ltAnimate);
                }, 600);
                var ltRemove = setTimeout(function() {
                    try {
                        loadingElement.parentNode.removeChild(loadingElement);
                    } catch (e) {
                        // Do nothing.
                    }
                    clearTimeout(ltRemove);
                }, 900);
                // Remove loading indicator off

                // show all button click listener on
                var showAllButtonElm = document.getElementById('IconPickerModal').getElementsByClassName('ip-show-all-icons')[0];
                showAllButtonElm.addEventListener('click', function() {
                    ipElement.classList.add('show-all');
                    this.parentNode.removeChild(this);
                }, false);
                // Show all button click listener off

                // close and remove all on
                var removeIpElement = function(delay) {
                    setTimeout(function() {
                        IconPickerM.hide();
                        IconPickerM.destroy();
                    }, delay);
                };
                // Close and remove all off

                // cancel button click listener on
                var cancelButtonElm = document.getElementById('IconPickerModal').getElementsByClassName('cancel')[0];
                cancelButtonElm.addEventListener('click', function() {
                    removeIpElement(400);
                }, false);
                // Cancel button click listener off

                // search input keyup listener on
                var searchInputElm = document.getElementById('IconPickerSearch');
                searchInputElm.addEventListener('keyup', function(e) {

                    // Keycodes
                    var eKeyCode = e.keyCode;
                    var eCode = e.code.toString().toLowerCase();

                    // Check space and backspace keyup
                    var spaceOrBackspace = false;
                    if (eKeyCode === 32 || eCode === 'space' || eKeyCode === 8 || eCode === 'backspace') {
                        spaceOrBackspace = true;
                    }

                    // Cant type space
                    if (eKeyCode === 32 || eCode === 'space') {
                        this.value = this.value.replace(' ', '');
                        return false;
                    }

                    // This val
                    var searchVal = this.value.toString().toLowerCase();

                    // Define icons areas
                    var firstIconsArea = document.getElementById('IconPickerModal').getElementsByClassName('ip-icons-area')[0];
                    var searchResultArea = document.getElementById('IconPickerModal').getElementsByClassName('ip-icons-search-results')[0];

                    // Clear old results
                    searchResultArea.innerHTML = '';

                    // (if not space or backspace) and (if typed at least one char) able to search
                    if (!spaceOrBackspace && searchVal.length > 0) {

                        // For "serch term" in Json
                        var tempIcons = '';
                        for (var key in jsonData) {
                            if (jsonData.hasOwnProperty(key)) {
                                var forObj = jsonData[key];
                                // If there is results create them
                                if (forObj.toString().indexOf(searchVal) > -1) {
                                    firstIconsArea.style.display = 'none';
                                    var tempIcon = '<i data-search="' + forObj + '" data-class="' + forObj + '" class="search-icon select-icon ' + forObj + '"></i>';
                                    tempIcons += tempIcon;
                                }
                            }
                        }

                        // Create a temp container
                        var tempResults = document.createElement('div');
                        tempResults.id = 'IconsTempResults';
                        tempResults.innerHTML = tempIcons;

                        // No results found on
                        if (tempIcons.length < 1) {
                            firstIconsArea.style.display = 'none';
                            var noResultsText = ipNewOptions.noResultsFound;
                            if (!noResultsText || (noResultsText && noResultsText.length < 1)) {
                                noResultsText = ipDefaultOptions.noResultsFound;
                            }
                            var noResultElm = '<p class="ip-no-results-found">' + noResultsText + '</p>';
                            tempResults.innerHTML = noResultElm;
                        }
                        // No results found off

                        // append temp container to results area
                        searchResultArea.appendChild(tempResults);

                        // Event listener for each temp icon
                        eachIconEventListener('search');

                    }
                    // Show first icons
                    else {
                        firstIconsArea.style.display = 'block';
                    }

                }, false);
                // Search input keyup listener off


                // each icon click listener on
                var eachIconEventListener = function(firstOrSearch) {

                    var inputElm = document.querySelectorAll(inputElement);
                    var previewElm = document.querySelectorAll(previewElement);

                    // Define icons on
                    var eachIconElm;
                    if (firstOrSearch === 'first') { // First
                        eachIconElm = document.getElementById('IconPickerModal').getElementsByClassName('first-icon');
                    } else if (firstOrSearch === 'search') { // Search
                        eachIconElm = document.getElementById('IconPickerModal').getElementsByClassName('search-icon');
                    }
                    // Define icons off

                    // add listeners each on
                    for (var i = 0; i < eachIconElm.length; i++) {
                        var singleIconElm = eachIconElm[i];
                        singleIconElm.addEventListener('click', function(e) {
                            e.preventDefault();
                            var iconClassName = this.dataset.class;

                            // Each input value on
                            for (var i = 0; i < inputElm.length; i++) {

                                var getTagName = inputElm[i].tagName.toString().toLowerCase();
                                // If an input or textarea element
                                if (getTagName === 'input' || getTagName === 'textarea') {
                                    inputElm[i].value = iconClassName;
                                }
                                // Else any of element
                                else {
                                    inputElm[i].innerHTML = iconClassName;
                                }

                            }
                            // Each input value off

                            // each preview on
                            for (var i = 0; i < previewElm.length; i++) {
                                previewElm[i].className = iconClassName;
                            }
                            // Each preview off

                            // callback on
                            if (theCallback) {
                                theCallback(iconClassName, target);
                            }
                            // Callback off

                            removeIpElement(400);
                        }, false);
                    }
                    // Add listeners each off

                };
                // Each icon click listener off

                // first icons listeners
                eachIconEventListener('first');
            });
        };
        // IconPicker: Append Library to Body off

        $(document).off('click', theButton);
        $(document).on('click', theButton, function() {
            getIconListXmlHttpRequest(jsonUrl, showAllButton, cancelButton, searchPlaceholder, borderRadius, inputElement, previewElement, theCallback, this);
        });
    },
};
// IconPicker: Main off
export default IconPicker;