// Contact form: international phone input (intl-tel-input), client-side file
// validation, and a multipart submit to /contact-handler.php. Progressive
// enhancement — the handler re-validates everything server-side.
(function () {
  var form = document.getElementById('contact-form');
  if (!form) return;

  var phoneInput = document.getElementById('cf-phone');
  var fileInput = document.getElementById('cf-files');
  var fileList = document.getElementById('cf-filelist');
  var statusEl = document.getElementById('cf-status');
  var submitBtn = document.getElementById('cf-submit');
  var iti = null;

  var MAX_FILES = 3;
  var MAX_TOTAL = 5 * 1024 * 1024; // 5 MB
  var ALLOWED_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp', 'gif'];

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
  function fieldError(msg) { setStatus(msg, 'error'); }

  function ext(name) { var p = name.split('.'); return p.length > 1 ? p.pop().toLowerCase() : ''; }
  function fmtSize(b) {
    return b >= 1048576 ? (Math.round(b / 1048576 * 10) / 10) + ' MB'
                        : Math.max(1, Math.round(b / 1024)) + ' KB';
  }

  // Returns an error string if the current selection is invalid, else null.
  function validateFiles() {
    if (!fileInput || !fileInput.files) return null;
    var files = fileInput.files;
    if (files.length > MAX_FILES) return 'Please attach no more than ' + MAX_FILES + ' files.';
    var total = 0;
    for (var i = 0; i < files.length; i++) {
      if (ALLOWED_EXT.indexOf(ext(files[i].name)) === -1) {
        return '"' + files[i].name + '" isn\'t an allowed type. Use a document or image.';
      }
      total += files[i].size;
    }
    if (total > MAX_TOTAL) return 'Attachments must total 5 MB or less.';
    return null;
  }

  function renderFileList() {
    if (!fileList) return;
    fileList.innerHTML = '';
    if (!fileInput.files) return;
    for (var i = 0; i < fileInput.files.length; i++) {
      var f = fileInput.files[i];
      var li = document.createElement('li');
      li.textContent = f.name + ' (' + fmtSize(f.size) + ')';
      fileList.appendChild(li);
    }
  }

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      renderFileList();
      var err = validateFiles();
      if (err) fieldError(err); else setStatus('', '');
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var name = form.name.value.trim();
    var email = form.email.value.trim();
    var message = form.message.value.trim();

    if (!name) return fieldError('Please enter your name.');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fieldError('Please enter a valid email address.');
    if (!message) return fieldError('Please enter a message.');

    if (iti && phoneInput.value.trim() && !iti.isValidNumber()) {
      return fieldError('Please enter a valid phone number for the selected country.');
    }

    var fileErr = validateFiles();
    if (fileErr) return fieldError(fileErr);

    // Build multipart body from the form, then normalise the phone to E.164.
    var fd = new FormData(form);
    fd.set('phone', iti && phoneInput.value.trim() ? iti.getNumber() : (phoneInput ? phoneInput.value.trim() : ''));

    submitBtn.disabled = true;
    setStatus('Sending…', 'pending');

    fetch('/contact-handler.php', { method: 'POST', body: fd })
      .then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
      })
      .then(function (result) {
        if (result.ok && result.data.success) {
          form.reset();
          renderFileList();
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
