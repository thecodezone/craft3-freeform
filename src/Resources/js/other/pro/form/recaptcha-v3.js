"use strict";!function(){var e="https://www.google.com/recaptcha/api.js?render={siteKey}",r="{formAnchor}",t=document.querySelector('form[data-id="'.concat(r,'"]'));if(!t)return void console.error('Recaptcha could not find form by ID "'.concat(r,'"'));if(!t.freeform)return void console.error("Form is not a Freeform form");t.freeform.addOnAfterAjaxSubmit(function(){o()});var o=function(){grecaptcha.ready(function(){grecaptcha.execute("{siteKey}",{action:"{action}"}).then(function(e){var r=document.querySelectorAll('*[name="g-recaptcha-response"]'),t=!0,o=!1,c=void 0;try{for(var a,n=r[Symbol.iterator]();!(t=(a=n.next()).done);t=!0){var i=a.value;i.value=e}}catch(f){o=!0,c=f}finally{try{t||null==n["return"]||n["return"]()}finally{if(o)throw c}}})})};if(Freeform.recaptchaV3Script)Freeform.recaptchaV3Script.addEventListener("load",o);else{var c=document.createElement("script");c.src=e,c.async=!0,c.defer=!0,c.addEventListener("load",o),document.body.appendChild(c),Freeform.recaptchaV3Script=c}}();