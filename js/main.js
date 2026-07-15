const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!prefersReducedMotion) {
    window.addEventListener('load', () => {
        const hero = document.querySelector('.hero-section');
        if (hero) {
            hero.style.opacity = '0';
            hero.style.transform = 'translateY(24px)';
            requestAnimationFrame(() => {
                hero.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                hero.style.opacity = '1';
                hero.style.transform = 'translateY(0)';
            });
        }
    });
}

const links = document.querySelectorAll('a[href^="#"]');
links.forEach((link) => {
    link.addEventListener('click', (event) => {
        const targetId = link.getAttribute('href').substring(1);
        const target = document.getElementById(targetId);
        if (target) {
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

const thumbnails = document.querySelectorAll('.thumbnail-button');
const mainImage = document.getElementById('product-main-image');
if (mainImage && thumbnails.length > 0) {
    thumbnails.forEach((button) => {
        button.addEventListener('click', () => {
            const imageUrl = button.dataset.image;
            const altText = button.dataset.alt;
            if (imageUrl) {
                mainImage.src = imageUrl;
            }
            if (altText) {
                mainImage.alt = altText;
            }
            thumbnails.forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
        });
    });
}

const copyButton = document.querySelector('.btn-share');
if (copyButton) {
    copyButton.addEventListener('click', async () => {
        const url = window.location.href;
        try {
            await navigator.clipboard.writeText(url);
            copyButton.textContent = 'Link copied';
            setTimeout(() => {
                copyButton.textContent = 'Copy product link';
            }, 2000);
        } catch (error) {
            console.error('Unable to copy link', error);
        }
    });
}

const financeTermInput = document.getElementById('finance-term');
const financeRateInput = document.getElementById('finance-rate');
const monthlyPaymentEl = document.getElementById('monthly-payment');

function calculateMonthlyPayment() {
    if (!financeTermInput || !financeRateInput || !monthlyPaymentEl) {
        return;
    }

    const term = Number(financeTermInput.value) || 36;
    const annualRate = Number(financeRateInput.value) / 100 || 0.12;
    const principal = (() => {
        const salePrice = document.querySelector('.price-sale');
        const basePrice = document.querySelector('.price:not(.price-original):not(.price-sale)');
        const value = salePrice ? Number(salePrice.textContent.replace(/[^0-9.]/g, '')) : Number(basePrice ? basePrice.textContent.replace(/[^0-9.]/g, '') : 0);
        return Number(value) || 0;
    })();

    const monthlyRate = annualRate / 12;
    const payment = monthlyRate > 0
        ? (principal * monthlyRate) / (1 - Math.pow(1 + monthlyRate, -term))
        : principal / term;

    monthlyPaymentEl.textContent = payment > 0 ? `R${payment.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : 'R0.00';
}

if (financeTermInput && financeRateInput) {
    financeTermInput.addEventListener('input', calculateMonthlyPayment);
    financeRateInput.addEventListener('input', calculateMonthlyPayment);
    calculateMonthlyPayment();
}

const compareButton = document.getElementById('compare-button');
if (compareButton) {
    compareButton.addEventListener('click', () => {
        compareButton.textContent = 'Compare feature coming soon';
        setTimeout(() => {
            compareButton.textContent = 'Compare';
        }, 2000);
    });
}

const cartForms = document.querySelectorAll('.product-action-form');
cartForms.forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Processing...';
        }
    });
});
