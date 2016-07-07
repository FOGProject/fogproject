$(function() {
    checkboxToggleSearchListPages();
    ProductUpdate();
    validateInputs('.groupname-input',/^[a-zA-Z0-9_- ]{1,255}$/);
});
