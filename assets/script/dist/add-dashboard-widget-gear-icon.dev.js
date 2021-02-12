"use strict";

window.onload = function () {
  var mainDiv = document.querySelector('#form_vibes_widget-0');

  if (mainDiv !== null) {
    var headerBar = document.querySelector('#form_vibes_widget-0 .ui-sortable-handle');
    var toggleIcon = document.createElement('span');
    var settingDiv = document.querySelector('#fv-dashboard-settings');
    toggleIcon.classList.add('dashicons', 'dashicons-admin-generic', 'fv-dashboard-toggle-icon');
    headerBar.appendChild(toggleIcon);

    toggleIcon.onclick = function () {
      mainDiv.classList.toggle('closed');
      settingDiv.classList.toggle('fv-dashboard-setting-none');
    };
  }
};