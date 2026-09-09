(() => {
    'use strict';

    const form = document.getElementById('billingCardForm');
    const dialog = document.getElementById('billingPaymentDialog');
    if (!form || !dialog || typeof window.MercadoPago !== 'function') return;

    const publicKey = form.dataset.publicKey || '';
    const planField = document.getElementById('billingPlan');
    const amountField = document.getElementById('billingAmount');
    const tokenField = document.getElementById('billingCardToken');
    const title = document.getElementById('billingPaymentTitle');
    const summary = document.getElementById('billingPaymentPlanSummary');
    const submit = document.getElementById('billingPaymentSubmit');
    const errorBox = document.getElementById('billingPaymentError');
    let submitting = false;

    const showError = () => {
        errorBox.hidden = false;
        submit.disabled = false;
        submitting = false;
    };

    const mp = new window.MercadoPago(publicKey, { locale: 'pt-BR' });
    const cardForm = mp.cardForm({
        amount: amountField.value,
        iframe: true,
        form: {
            id: 'billingCardForm',
            cardNumber: { id: 'form-checkout__cardNumber', placeholder: 'Número do cartão' },
            expirationDate: { id: 'form-checkout__expirationDate', placeholder: 'MM/AA' },
            securityCode: { id: 'form-checkout__securityCode', placeholder: 'CVV' },
            cardholderName: { id: 'form-checkout__cardholderName', placeholder: 'Como está no cartão' },
            issuer: { id: 'form-checkout__issuer' },
            installments: { id: 'form-checkout__installments' },
            identificationType: { id: 'form-checkout__identificationType' },
            identificationNumber: { id: 'form-checkout__identificationNumber', placeholder: 'Somente números' },
            cardholderEmail: { id: 'form-checkout__cardholderEmail', placeholder: 'E-mail do pagador' },
        },
        callbacks: {
            onFormMounted: (error) => { if (error) showError(); },
            onSubmit: (event) => {
                event.preventDefault();
                if (submitting) return;
                const data = cardForm.getCardFormData();
                if (!data.token) { showError(); return; }
                tokenField.value = data.token;
                errorBox.hidden = true;
                submitting = true;
                submit.disabled = true;
                submit.textContent = 'Validando com o Mercado Pago...';
                form.submit();
            },
            onCardTokenReceived: (error) => { if (error) showError(); },
        },
    });

    document.querySelectorAll('[data-billing-plan]').forEach((button) => {
        button.addEventListener('click', () => {
            planField.value = button.dataset.billingPlan || 'monthly';
            amountField.value = button.dataset.billingAmount || '59.90';
            title.textContent = planField.value === 'annual' ? 'Assinar plano anual' : 'Assinar plano mensal';
            summary.textContent = planField.value === 'annual' ? 'Plano anual · R$ 599,00 por ano' : 'Plano mensal · R$ 59,90 por mês';
            errorBox.hidden = true;
            dialog.showModal();
        });
    });

    document.querySelector('[data-close-billing]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
})();

