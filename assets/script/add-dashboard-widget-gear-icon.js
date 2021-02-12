window.onload = () => {
  const mainDiv = document.querySelector('#form_vibes_widget-0');

  if (mainDiv !== null) {
    const headerBar = document.querySelector(
      '#form_vibes_widget-0 .ui-sortable-handle'
    );
    const toggleIcon = document.createElement('span');
    const settingDiv = document.querySelector('#fv-dashboard-settings');

    toggleIcon.classList.add(
      'dashicons',
      'dashicons-admin-generic',
      'fv-dashboard-toggle-icon'
    );
    headerBar.appendChild(toggleIcon);

    toggleIcon.onclick = () => {
      mainDiv.classList.toggle('closed');
      settingDiv.classList.toggle('fv-dashboard-setting-none');
    };
  }
};
