"use strict";var gRecaptchaTargetForm=null,gRecaptchaTokenIsSet=!1;window.updateRecaptchaToken||(window.updateRecaptchaToken=function(e){var r=document.querySelectorAll('*[name="g-recaptcha-response"]'),t=!0,a=!1,c=void 0;try{for(var o,n=r[Symbol.iterator]();!(t=(o=n.next()).done);t=!0){var i=o.value;i.value=e}}catch(p){a=!0,c=p}finally{try{t||null==n["return"]||n["return"]()}finally{if(a)throw c}}gRecaptchaTokenIsSet||(gRecaptchaTokenIsSet=!0,gRecaptchaTargetForm.dispatchEvent(new Event("submit")))}),function(){var e="https://www.google.com/recaptcha/api.js",r="{formAnchor}",t=document.querySelector('form[data-id="'.concat(r,'"]'));if(!t)return void console.error('Recaptcha could not find form by ID "'.concat(r,'"'));if(!t.freeform)return void console.error("Form is not a Freeform form");if(t.freeform.addOnSubmitCallback(function(e){return gRecaptchaTargetForm=e,grecaptcha.execute(),gRecaptchaTokenIsSet}),t.freeform.addOnAfterAjaxSubmit(function(){gRecaptchaTokenIsSet=!1,grecaptcha.reset()}),!Freeform.recaptchaInvisibleScript){var a=document.createElement("script");a.src=e,a.async=!0,a.defer=!0,document.body.appendChild(a),Freeform.recaptchaInvisibleScript=a}}();