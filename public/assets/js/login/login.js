// ESAT — comportamiento propio de la página de login (mostrar/ocultar contraseña).
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.login-toggle-password');
    var input = document.getElementById('password');

    if (!toggle || !input) return;

    toggle.addEventListener('click', function () {
        var isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';
        toggle.classList.toggle('is-visible', !isVisible);
        toggle.setAttribute('aria-label', isVisible ? 'Mostrar contraseña' : 'Ocultar contraseña');
    });
});
