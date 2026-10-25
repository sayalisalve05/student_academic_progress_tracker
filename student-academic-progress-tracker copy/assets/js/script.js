document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById("togglePassword");
    const password = document.getElementById("password");
    if (toggle && password) {
        toggle.addEventListener("click", function () {
            password.type = password.type === "password" ? "text" : "password";
            toggle.textContent = password.type === "password" ? "👁" : "🙈";
        });
    }
});