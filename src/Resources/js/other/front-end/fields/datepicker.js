"use strict";!function(){var e={js:"//cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.1/flatpickr.min.js",css:"//cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.1/flatpickr.min.css",locale:"//cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.1/l10n/{locale}.js"},t="{formAnchor}",a=!1,r=!1,i=document.querySelector('form[data-id="'.concat(t,'"]'));if(!i)return void console.error('Datepicker could not find form by ID "'.concat(t,'"'));if(!i.freeform)return void console.error("Form is not a Freeform form");var c=function(){if(a&&r){var e=i.querySelectorAll("*[data-datepicker-enabled]"),t=!0,c=!1,l=void 0;try{for(var d,n=e[Symbol.iterator]();!(t=(d=n.next()).done);t=!0){var o=d.value,m={disableMobile:!0,allowInput:!0,dateFormat:o.getAttribute("data-datepicker-format"),enableTime:null!==o.getAttribute("data-datepicker-enabletime"),noCalendar:null===o.getAttribute("data-datepicker-enabledate"),time_24hr:null!==o.getAttribute("data-datepicker-clock_24h"),minDate:o.getAttribute("data-datepicker-min-date"),maxDate:o.getAttribute("data-datepicker-max-date"),minuteIncrement:1,hourIncrement:1,locale:o.getAttribute("data-datepicker-locale"),"static":null!==o.getAttribute("data-datepicker-static")},s=new CustomEvent("flatpickr-before-init",{detail:m});i.dispatchEvent(s);var u=flatpickr(o,s.detail),p=new CustomEvent("flatpickr-ready",{detail:u});i.dispatchEvent(p)}}catch(f){c=!0,l=f}finally{try{t||null==n["return"]||n["return"]()}finally{if(c)throw l}}}},l=function(){a=!0,c()},d=function(){r=!0,c()};if(Freeform.datepickerScript)Freeform.datepickerScript.addEventListener("load",l),Freeform.datepickerLocaleScript.addEventListener("load",d);else{var n=document.createElement("script");n.src=e.js,n.addEventListener("load",l),document.body.appendChild(n);var o=document.createElement("script");o.src=e.locale,o.addEventListener("load",d),document.body.appendChild(o);var m=document.createElement("link");m.rel="stylesheet",m.type="text/css",m.media="all",m.href=e.css,document.getElementsByTagName("head")[0].appendChild(m),Freeform.datepickerScript=n,Freeform.datepickerLocaleScript=o}}();