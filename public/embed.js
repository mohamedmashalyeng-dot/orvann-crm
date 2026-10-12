/*
 * Embed the CRM form on any website:
 *   <div id="crm-form"></div>
 *   <script src="https://YOUR-CRM-DOMAIN/embed.js"></script>
 */
(function () {
  var script = document.currentScript;
  var base = new URL('.', script.src); // the folder this script was loaded from
  var page = new URLSearchParams(location.search);
  var src = page.get('utm_source') || location.hostname;

  var iframe = document.createElement('iframe');
  iframe.src = base.href + '?embed=1&source=' + encodeURIComponent(src);
  iframe.title = 'نموذج التسجيل';
  iframe.loading = 'lazy';
  iframe.style.cssText = 'width:100%;border:0;min-height:640px;display:block';

  var target = document.getElementById(script.getAttribute('data-target') || 'crm-form');
  if (target) target.appendChild(iframe);
  else script.parentNode.insertBefore(iframe, script);

  window.addEventListener('message', function (e) {
    if (e.origin === base.origin && e.source === iframe.contentWindow && e.data && e.data.crmFormHeight) {
      iframe.style.minHeight = '0';
      iframe.style.height = e.data.crmFormHeight + 'px';
    }
  });
})();
