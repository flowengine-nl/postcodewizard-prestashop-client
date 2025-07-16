/**
 * PostcodeWizard Module
 *
 * @author    Xeropex
 * @copyright Copyright (c) 2025 Xeropex
 * @license   https://opensource.org/licenses/MIT MIT License
 */

document.addEventListener('DOMContentLoaded', function () {
    if (typeof postcodewizard_mode === 'undefined') return;
    const mode = postcodewizard_mode;

    if (mode === 'lookup') {
        addCustomAddressFields();
        const postcodeInput = document.querySelector('input[name="postcode"]');
        const houseNumberInput = document.querySelector('#postcodewizard-house-number');

        if (postcodeInput && houseNumberInput) {
            [postcodeInput, houseNumberInput].forEach(input => {
                input.addEventListener('blur', triggerLookupIfReady);
            });
        }
    }

    if (mode === 'autocomplete') {
        setupAutocompleteMode();
    }
});

function addCustomAddressFields() {
    const addressField = document.querySelector('input[name="address1"]');
    const postcodeField = document.querySelector('input[name="postcode"]');
    if (!addressField || !postcodeField) return;

    const addressGroup = addressField.closest('.form-group');
    const postcodeGroup = postcodeField.closest('.form-group');
    if (!addressGroup || !postcodeGroup) return;
    if (addressGroup.parentElement.querySelector('#postcodewizard-house-number')) return;

    addressGroup.style.display = 'none';

    addressField.type = 'hidden';
    const addressLabel = addressGroup.querySelector('label');
    if (addressLabel) addressLabel.style.display = 'none';

    const container = addressGroup.parentElement;
    const houseNumberGroup = postcodeGroup.cloneNode(true);
    const streetGroup = postcodeGroup.cloneNode(true);

    const houseNumberInput = houseNumberGroup.querySelector('input');
    const houseNumberLabel = houseNumberGroup.querySelector('label');
    houseNumberInput.name = 'postcodewizard_house_number';
    houseNumberInput.id = 'postcodewizard-house-number';
    houseNumberInput.value = '';
    houseNumberInput.required = true;
    houseNumberLabel.setAttribute('for', 'postcodewizard-house-number');
    houseNumberLabel.innerText = 'Huisnummer';

    const streetInput = streetGroup.querySelector('input');
    const streetLabel = streetGroup.querySelector('label');
    streetInput.name = 'postcodewizard-street';
    streetInput.id = 'postcodewizard-street';
    streetInput.type = 'text';
    streetInput.required = true;
    streetInput.value = '';
    streetLabel.setAttribute('for', 'postcodewizard-street');
    streetLabel.innerText = 'Straat';

    const additionGroup = container.querySelector('input[name="address2"]')?.closest('.form-group');
    container.insertBefore(houseNumberGroup, postcodeGroup.nextSibling);
    container.insertBefore(streetGroup, houseNumberGroup.nextSibling);
    if (additionGroup) {
        container.insertBefore(additionGroup, streetGroup.nextSibling);
    }

    const originalAddress = addressField.value?.trim();
    if (originalAddress) {
        const parts = originalAddress.match(/^(.*?)(\d{1,4}\s*\w*)$/);
        if (parts) {
            streetInput.value = parts[1].trim();
            houseNumberInput.value = parts[2].trim();
        } else {
            streetInput.value = originalAddress;
            houseNumberInput.value = '';
        }
    }
}

function triggerLookupIfReady() {
    const postcode = document.querySelector('input[name="postcode"]')?.value.replace(/\s+/g, '').toUpperCase();
    const houseNumberInput = document.querySelector('#postcodewizard-house-number');
    const houseNumber = houseNumberInput?.value.trim();
    if (!postcode || !houseNumber || postcode.length < 6) return;

    setTimeout(() => {
        lookupAddress(postcode, houseNumber);
    }, 1000);
}

function lookupAddress(postcode, houseNumber) {
    if (!postcode || !houseNumber) return;

    fetch(`/module/postcodewizard/lookup?postcode=${encodeURIComponent(postcode)}&houseNumber=${encodeURIComponent(houseNumber)}`)
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(data => {
            if (!data || !data.street || !data.city) throw new Error('Ongeldig antwoord');

            const streetField = document.querySelector('#postcodewizard-street');
            const cityField = document.querySelector('input[name="city"]');
            const postcodeField = document.querySelector('input[name="postcode"]');
            const addressField = document.querySelector('input[name="address1"]');

            if (streetField) streetField.value = data.street;
            if (cityField) cityField.value = data.city;
            if (postcodeField) postcodeField.value = formatPostcode(postcode);
            if (addressField) addressField.value = `${data.street} ${houseNumber}`.trim();
        })
        .catch(err => {
            console.warn('[PostcodeWizard] Lookup mislukt of ongeldig adres:', err);
        });
}

document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form || !form.querySelector) return;

    const street = document.querySelector('#postcodewizard-street')?.value ?? '';
    const houseNumber = document.querySelector('#postcodewizard-house-number')?.value ?? '';
    const addressField = form.querySelector('input[name="address1"]');

    if (addressField && street && houseNumber) {
        addressField.value = `${street} ${houseNumber}`.trim();
    }
}, true);

function formatPostcode(postcode) {
    postcode = postcode.replace(/\s+/g, '').toUpperCase();
    return postcode.length === 6
        ? postcode.slice(0, 4) + ' ' + postcode.slice(4)
        : postcode;
}

function setupAutocompleteMode() {
    const addressInput = document.querySelector('input[name="address1"]');
    const postcodeField = document.querySelector('input[name="postcode"]');
    const cityField = document.querySelector('input[name="city"]');

    if (!addressInput) return;

    let debounceTimer;
    let suggestionBox;

    addressInput.setAttribute('autocomplete', 'off');

    addressInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length < 3) return;

        debounceTimer = setTimeout(() => fetchSuggestions(query), 300);
    });

    function fetchSuggestions(query) {
        fetch(`/module/postcodewizard/autocomplete?query=${encodeURIComponent(query)}`)
            .then(res => res.ok ? res.json() : Promise.reject(res))
            .then(data => showSuggestions(data ?? []))
            .catch(err => console.warn('[PostcodeWizard] Autocomplete mislukt', err));
    }

    function showSuggestions(suggestions) {
        if (suggestionBox) suggestionBox.remove();

        suggestionBox = document.createElement('ul');
        suggestionBox.className = 'pw-suggestion-box';

        suggestions.forEach(s => {
            const item = document.createElement('li');
            item.textContent = `${s.street} ${s.full_number}, ${s.postcode} ${s.city}`;
            item.addEventListener('click', () => selectSuggestion(s));
            suggestionBox.appendChild(item);
        });

        document.body.appendChild(suggestionBox);

        const rect = addressInput.getBoundingClientRect();
        suggestionBox.style.position = 'absolute';
        suggestionBox.style.left = `${rect.left + window.scrollX}px`;
        suggestionBox.style.top = `${rect.bottom + window.scrollY}px`;
        suggestionBox.style.width = `${rect.width}px`;
    }

    function selectSuggestion(s) {
        addressInput.value = s.street + ' ' + (s.full_number ?? '');
        if (postcodeField) postcodeField.value = formatPostcode(s.postcode ?? '');
        if (cityField) cityField.value = s.city ?? '';
        if (suggestionBox) suggestionBox.remove();
    }
}
