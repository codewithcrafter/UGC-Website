(function () {
'use strict';
document.addEventListener('DOMContentLoaded', function () {
const form = document.getElementById('quickEnquiryForm');
if (!form) return;
const getApiEndpoint = () => {
const scriptTag = document.querySelector('script[src*="quick-enquiry.js"]');
if (scriptTag) {
const src = scriptTag.getAttribute('src') || '';
if (src.startsWith('../')) {
return '../api/quick-enquiry.php';
}
}
if (window.location.pathname.includes('/services/')) {
return '../api/quick-enquiry.php';
}
return 'api/quick-enquiry.php';
};
const apiEndpoint = getApiEndpoint();
const nameInput = document.getElementById('qeName');
const emailInput = document.getElementById('qeEmail');
const phoneInput = document.getElementById('qePhone');
const messageInput = document.getElementById('qeMessage');
const submitBtn = document.getElementById('qeSubmitBtn');
const btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
const btnSpinner = submitBtn ? submitBtn.querySelector('.btn-spinner') : null;
const csrfInput = document.getElementById('quickCsrfToken');
const successAlert = document.getElementById('qeSuccessAlert');
const errorAlert = document.getElementById('qeErrorAlert');
const errorMsgEl = document.getElementById('qeErrorMessage');
const nameError = document.getElementById('qeNameError');
const emailError = document.getElementById('qeEmailError');
const phoneError = document.getElementById('qePhoneError');
const messageError = document.getElementById('qeMessageError');
const fetchCsrfToken = () => {
fetch(apiEndpoint, { method: 'GET', credentials: 'same-origin' })
.then(res => res.json())
.then(data => {
if (data && data.csrf_token && csrfInput) {
csrfInput.value = data.csrf_token;
}
})
.catch(() => {
});
};
fetchCsrfToken();
const validateIndianPhone = (raw) => {
const clean = (raw || '').replace(/[\s\-\(\)\.]/g, '');
const regex = /^(?:(?:\+|0{0,2})91)?[6789]\d{9}$/;
return regex.test(clean);
};
const clearErrors = () => {
[nameError, emailError, phoneError, messageError].forEach(el => {
if (el) { el.textContent = ''; el.style.display = 'none'; }
});
[nameInput, emailInput, phoneInput, messageInput].forEach(inp => {
if (inp) inp.classList.remove('input-error');
});
if (errorAlert) errorAlert.style.display = 'none';
};
const showError = (errorEl, inputEl, message) => {
if (errorEl) {
errorEl.textContent = message;
errorEl.style.display = 'block';
}
if (inputEl) {
inputEl.classList.add('input-error');
}
};
[nameInput, emailInput, phoneInput, messageInput].forEach(inp => {
if (!inp) return;
inp.addEventListener('input', () => {
inp.classList.remove('input-error');
const err = document.getElementById(inp.id + 'Error');
if (err) { err.textContent = ''; err.style.display = 'none'; }
if (errorAlert) errorAlert.style.display = 'none';
});
});
form.addEventListener('submit', function (e) {
e.preventDefault();
clearErrors();
const nameVal = nameInput ? nameInput.value.trim() : '';
const emailVal = emailInput ? emailInput.value.trim() : '';
const phoneVal = phoneInput ? phoneInput.value.trim() : '';
const messageVal = messageInput ? messageInput.value.trim() : '';
let hasError = false;
if (!nameVal) {
showError(nameError, nameInput, 'Please enter your name.');
hasError = true;
} else if (nameVal.length < 2) {
showError(nameError, nameInput, 'Name must be at least 2 characters.');
hasError = true;
}
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
if (!emailVal) {
showError(emailError, emailInput, 'Please enter your email address.');
hasError = true;
} else if (!emailRegex.test(emailVal)) {
showError(emailError, emailInput, 'Please enter a valid email address.');
hasError = true;
}
if (!phoneVal) {
showError(phoneError, phoneInput, 'Please enter your phone number.');
hasError = true;
} else if (!validateIndianPhone(phoneVal)) {
showError(phoneError, phoneInput, 'Enter a valid 10-digit Indian mobile number (e.g. 9876543210).');
hasError = true;
}
if (hasError) {
if (errorAlert) {
if (errorMsgEl) errorMsgEl.textContent = 'Please correct the highlighted fields above.';
errorAlert.style.display = 'flex';
}
return;
}
if (submitBtn) submitBtn.disabled = true;
if (btnText) btnText.textContent = 'SENDING ENQUIRY...';
if (btnSpinner) btnSpinner.style.display = 'inline-block';
const formData = new FormData(form);
fetch(apiEndpoint, {
method: 'POST',
body: formData,
credentials: 'same-origin',
headers: {
'X-Requested-With': 'XMLHttpRequest'
}
})
.then(async response => {
const data = await response.json().catch(() => ({
success: false,
message: 'Server returned an invalid response. Please try again.'
}));
if (response.ok && data.success) {
form.reset();
if (successAlert) {
successAlert.style.display = 'flex';
successAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
setTimeout(() => {
if (submitBtn) submitBtn.disabled = false;
if (btnText) btnText.textContent = 'SEND ENQUIRY';
if (btnSpinner) btnSpinner.style.display = 'none';
}, 1500);
fetchCsrfToken();
} else {
if (data.errors) {
if (data.errors.name) showError(nameError, nameInput, data.errors.name);
if (data.errors.email) showError(emailError, emailInput, data.errors.email);
if (data.errors.phone) showError(phoneError, phoneInput, data.errors.phone);
if (data.errors.message) showError(messageError, messageInput, data.errors.message);
}
if (errorAlert) {
if (errorMsgEl) errorMsgEl.textContent = data.message || 'Submission failed. Please check the fields and try again.';
errorAlert.style.display = 'flex';
}
if (submitBtn) submitBtn.disabled = false;
if (btnText) btnText.textContent = 'SEND ENQUIRY';
if (btnSpinner) btnSpinner.style.display = 'none';
}
})
.catch(err => {
console.error('Quick Enquiry Submission error:', err);
if (errorAlert) {
if (errorMsgEl) errorMsgEl.textContent = 'Network or server error. Please try again or contact us directly.';
errorAlert.style.display = 'flex';
}
if (submitBtn) submitBtn.disabled = false;
if (btnText) btnText.textContent = 'SEND ENQUIRY';
if (btnSpinner) btnSpinner.style.display = 'none';
});
});
});
})();
