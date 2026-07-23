// Contact form: international phone input (intl-tel-input) + AJAX submit to
// /contact-handler.php. Progressive enhancement — if JS or the library fails
// to load, the form still submits its fields (the handler validates server-side).
(function () {
  var form = document.getElementById('contact-form');
  if (!form) return;

  var phoneInput = document.getElementById('cf-phone');
  var statusEl = document.getElementById('cf-status');
  var submitBtn = document.getElementById('cf-submit');
  var iti = null;

  // Initialise the phone input with an IP-based default country.
  if (phoneInput && window.intlTelInput) {
    iti = window.intlTelInput(phoneInput, {
      initialCountry: 'auto',
      separateDialCode: true,
      geoIpLookup: function (callback) {
        fetch('/contact-geo.php')
          .then(function (r) { return r.json(); })
          .then(function (d) { callback(d && d.country ? d.country : 'ae'); })
          .catch(function () { callback('ae'); });
      }
    });
  }

  function setStatus(msg, kind) {
    statusEl.textContent = msg;
    statusEl.className = 'cf-status' + (kind ? ' cf-status--' + kind : '');
  }

  function fieldError(msg) {
    setStatus(msg, 'error');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var name = form.name.value.trim();
    var email = form.email.value.trim();
    var message = form.message.value.trim();

    if (!name) return fieldError('Please enter your name.');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fieldError('Please enter a valid email address.');
    if (!message) return fieldError('Please enter a message.');

    // Phone is optional, but if provided it must be valid for the chosen country.
    var phone = '';
    if (iti && phoneInput.value.trim()) {
      if (!iti.isValidNumber()) return fieldError('Please enter a valid phone number for the selected country.');
      phone = iti.getNumber(); // E.164, e.g. +971501234567
    } else if (!iti) {
      phone = phoneInput ? phoneInput.value.trim() : '';
    }

    var payload = {
      name: name,
      email: email,
      phone: phone,
      company: form.company.value.trim(),
      subject: form.subject.value.trim(),
      message: message,
      website: form.website.value // honeypot
    };

    submitBtn.disabled = true;
    setStatus('Sending…', 'pending');

    fetch('/contact-handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
      })
      .then(function (result) {
        if (result.ok && result.data.success) {
          form.reset();
          if (iti) iti.setCountry(iti.getSelectedCountryData().iso2 || 'ae');
          setStatus('Thanks — your message has been sent. I\'ll get back to you soon.', 'success');
        } else {
          fieldError(result.data.error || 'Something went wrong. Please try again.');
        }
      })
      .catch(function () {
        fieldError('Network error. Please try again, or email hello@pandit.guru directly.');
      })
      .finally(function () {
        submitBtn.disabled = false;
      });
  });
})();
