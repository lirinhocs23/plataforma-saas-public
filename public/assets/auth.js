document.querySelectorAll('.alternar-senha').forEach((button) => {
    button.addEventListener('click', () => {
        const input = button.parentElement.querySelector('input');
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.textContent = visible ? 'Mostrar' : 'Ocultar';
        button.setAttribute('aria-label', visible ? 'Mostrar senha' : 'Ocultar senha');
    });
});

const password = document.querySelector('#newPassword');
const confirmation = document.querySelector('#confirmPassword');
const passwordStatus = document.querySelector('#passwordStatus');
const confirmStatus = document.querySelector('#confirmStatus');

[passwordStatus, confirmStatus].forEach((status) => {
    status?.setAttribute('role', 'status');
    status?.setAttribute('aria-live', 'polite');
});

function updatePasswordStatus() {
    if (!password || !confirmation || !passwordStatus || !confirmStatus) return;

    const length = Array.from(password.value).length;
    const longEnough = length >= 15;
    passwordStatus.textContent = longEnough ? `${length} caracteres — comprimento válido.` : `${length} de 15 caracteres`;
    passwordStatus.className = longEnough ? 'valido' : 'invalido';

    const matches = confirmation.value !== '' && password.value === confirmation.value;
    confirmStatus.textContent = matches ? 'As senhas são iguais.' : 'A confirmação deve ser igual.';
    confirmStatus.className = matches ? 'valido' : 'invalido';
    confirmation.setCustomValidity(confirmation.value !== '' && !matches ? 'As senhas não são iguais.' : '');
}

password?.addEventListener('input', updatePasswordStatus);
confirmation?.addEventListener('input', updatePasswordStatus);
updatePasswordStatus();

const businessType = document.querySelector('#registerForm [name="business_type"]');
const foodPresetField = document.getElementById('foodPresetField');
function updateFoodPreset() {
    if (!businessType || !foodPresetField) return;
    const food = businessType.value === 'Alimentação';
    foodPresetField.hidden = !food;
    foodPresetField.style.display = food ? '' : 'none';
    foodPresetField.querySelector('select').disabled = !food;
}
businessType?.addEventListener('change', updateFoodPreset);
updateFoodPreset();


