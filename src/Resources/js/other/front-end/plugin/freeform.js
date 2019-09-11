"use strict";function _objectSpread(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{},n=Object.keys(r);"function"==typeof Object.getOwnPropertySymbols&&(n=n.concat(Object.getOwnPropertySymbols(r).filter(function(e){return Object.getOwnPropertyDescriptor(r,e).enumerable}))),n.forEach(function(t){_defineProperty(e,t,r[t])})}return e}function _classCallCheck(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function _defineProperties(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}function _createClass(e,t,r){return t&&_defineProperties(e.prototype,t),r&&_defineProperties(e,r),e}function _defineProperty(e,t,r){return t in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}var Freeform=function(){function e(t,r){var n=this;if(_classCallCheck(this,e),_defineProperty(this,"form",void 0),_defineProperty(this,"options",{ajax:!1,disableSubmit:!1,scrollToAnchor:!1,prevButtonName:"form_previous_page_button",successBannerMessage:"Form has been submitted successfully!",errorBannerMessage:"Error! Please review the form and try submitting again.",errorClassBanner:"ff-form-errors",errorClassList:"ff-errors",errorClassField:"ff-has-errors",successClassBanner:"ff-form-success",removeMessages:null,renderSuccess:null,renderFormErrors:null,renderFieldErrors:null}),_defineProperty(this,"_beforeSubmitCallbackStack",[]),_defineProperty(this,"_successfulAjaxSubmitCallbackStack",[]),_defineProperty(this,"_failedAjaxSubmitCallbackStack",[]),_defineProperty(this,"_afterAjaxSubmitCallbackStack",[]),_defineProperty(this,"setOption",function(e,t){n.options[e]=t}),_defineProperty(this,"lockSubmit",function(){n._getSubmitButtons().forEach(function(e){e.disabled=!0})}),_defineProperty(this,"unlockSubmit",function(){n._getSubmitButtons().forEach(function(e){e.disabled=!1})}),_defineProperty(this,"_setInstances",function(){var t=n.form;e.instances.set(t,n),t.freeform=n}),_defineProperty(this,"_emitInitEvent",function(){var e=n.form,t=n._createNewEvent("freeform-ready");e.dispatchEvent(t)}),_defineProperty(this,"_attachListeners",function(){n.form.addEventListener("submit",n._onSubmit);var e=n.form.querySelectorAll("input, select, textarea");e.forEach(function(e){e.addEventListener("change",function(e){n._removeMessageFrom(e.target)})})}),_defineProperty(this,"_onSubmit",function(t){n.lockSubmit();var r=n.form,o=n.options,s=!0,i=n.form.querySelector("[type=submit]:focus"),a=!1;return i&&i.name&&i.name===e._BACK_BUTTON_NAME&&(a=!0),n._beforeSubmitCallbackStack.forEach(function(e){var t=e.bind(n);t(r,o,a)||(s=!1)}),s?o.ajax?(t.preventDefault(),t.stopPropagation(),n._onSubmitAjax(t)):(n.form.submit(),!0):(t.preventDefault(),t.stopPropagation(),!1)}),_defineProperty(this,"_removeMessages",function(){if("function"==typeof n.options.removeMessages)return n.options.removeMessages=n.options.removeMessages.bind(n),n.options.removeMessages();var e=n.form,t=n.options,r=t.successClassBanner,o=t.errorClassBanner,s=t.errorClassList,i=t.errorClassField;e.querySelectorAll(".".concat(n._getClassArray(s).join("."))).remove();for(var a=e.querySelectorAll(".".concat(n._getClassArray(i).join("."))),c=0;c<a.length;c++){var u=a[c];n._removeMessageFrom(u)}e.querySelectorAll(".".concat(n._getClassArray(r).join("."))).remove(),document.querySelectorAll(".".concat(n._getClassArray(o).join("."))).remove()}),_defineProperty(this,"_removeMessageFrom",function(e){var t=n.options,r=t.errorClassList,o=t.errorClassField;n._removeClass(e,o);var s=e.parentNode.querySelector(".".concat(r));s&&s.remove()}),_defineProperty(this,"_renderSuccessBanner",function(){if("function"==typeof n.options.renderSuccess)return n.options.renderSuccess=n.options.renderSuccess.bind(n),n.options.renderSuccess();var e=n.form,t=n.options,r=t.successBannerMessage,o=t.successClassBanner,s=document.createElement("div");n._addClass(s,o);var i=document.createElement("p");i.appendChild(document.createTextNode(r)),s.appendChild(i),e.insertBefore(s,e.childNodes[0])}),_defineProperty(this,"_renderFieldErrors",function(e){if("function"==typeof n.options.renderFieldErrors)return n.options.renderFieldErrors=n.options.renderFieldErrors.bind(n),n.options.renderFieldErrors(e);var t=n.form,r=n.options,o=r.errorClassList,s=r.errorClassField;for(var i in e)if(e.hasOwnProperty(i)&&i){var a=e[i],c=document.createElement("ul");n._addClass(c,o);for(var u=0;u<a.length;u++){var l=a[u],f=document.createElement("li");f.appendChild(document.createTextNode(l)),c.appendChild(f)}for(var d=t.querySelectorAll("*[name="+i+"], *[name='"+i+"[]']"),m=0;m<d.length;m++){var p=d[m];n._addClass(p,s),p.parentElement.appendChild(c)}}}),_defineProperty(this,"_renderFormErrors",function(e){if("function"==typeof n.options.renderFormErrors)return n.options.renderFormErrors=n.options.renderFormErrors.bind(n),n.options.renderFormErrors(e);var t=n.form,r=n.options,o=r.errorClassBanner,s=r.errorBannerMessage,i=document.createElement("div");n._addClass(i,o);var a=document.createElement("p");if(a.appendChild(document.createTextNode(s)),i.appendChild(a),e.length){for(var c=document.createElement("ul"),u=0;u<e.length;u++){var l=e[u],f=document.createElement("li");f.appendChild(document.createTextNode(l)),c.appendChild(f)}i.appendChild(c)}t.insertBefore(i,t.childNodes[0])}),_defineProperty(this,"_onSuccessfulSubmit",function(e,t,r){n._successfulAjaxSubmitCallbackStack.forEach(function(n){return n(e,t,r)})}),_defineProperty(this,"_onFailedSubmit",function(e,t,r){n._failedAjaxSubmitCallbackStack.forEach(function(n){return n(e,t,r)})}),_defineProperty(this,"_onAfterSubmit",function(e,t,r){n._afterAjaxSubmitCallbackStack.forEach(function(n){return n(e,t,r)})}),_defineProperty(this,"_onSubmitAjax",function(e){var t=n.form,r=new FormData(t),o=new XMLHttpRequest;if(n._isSafari())for(var s=0;s<t.elements.length;s++)if("file"===t.elements[s].type&&""===t.elements[s].value){var i=t.elements[s];r["delete"](i.name)}var a=t.getAttribute("method"),c=t.getAttribute("action");return o.open(a,c?c:window.location.href,!0),o.setRequestHeader("Cache-Control","no-cache"),o.setRequestHeader("X-Requested-With","XMLHttpRequest"),o.setRequestHeader("HTTP_X_REQUESTED_WITH","XMLHttpRequest"),o.onload=function(){if(n._removeMessages(),200===o.status){var r=JSON.parse(o.response),s=r.success,i=r.finished,a=r.actions,c=void 0===a?[]:a,u=r.errors,l=r.formErrors,f=r.honeypot;if(c.length||(s&&i?(t.reset(),n._onSuccessfulSubmit(e,t,r),n._renderSuccessBanner()):(u||l)&&(n._onFailedSubmit(e,t,r),n._renderFieldErrors(u),n._renderFormErrors(l))),f){var d=t.querySelector("input[name^=freeform_form_handle]");d&&(d.setAttribute("name",f.name),d.setAttribute("id",f.name),d.value=f.hash)}n._onAfterSubmit(e,t,r)}else console.error(o);n.unlockSubmit(t)},o.send(r),!1}),_defineProperty(this,"_getSubmitButtons",function(){var e=n.options,t=e.disableSubmit,r=e.prevButtonName;return t?n.form.querySelectorAll('*[type=submit]:not([name="'.concat(r,'"])')):[]}),_defineProperty(this,"_isSafari",function(){return navigator.userAgent.indexOf("Safari")>-1}),_defineProperty(this,"_getClassArray",function(e){return"string"==typeof e&&(e=e.split(" ")),e}),_defineProperty(this,"_addClass",function(e,t){n._getClassArray(t).forEach(function(t){return e.classList.add(t)})}),_defineProperty(this,"_removeClass",function(e,t){"string"==typeof t&&(t=t.split(" ")),t.forEach(function(t){return e.classList.remove(t)})}),_defineProperty(this,"_createNewEvent",function(e){var t,r=!(arguments.length>1&&void 0!==arguments[1])||arguments[1],n=!(arguments.length>2&&void 0!==arguments[2])||arguments[2];return"function"==typeof Event?t=new Event(e,{bubbles:r,cancelable:n}):(t=document.createEvent("Event"),t.initEvent(e,!0,!0)),t}),this.form=t,this.options=_objectSpread({},this.options,r),!this.form)return void console.error('Could not find a form with ID "'.concat(id,'"'));if(this._attachListeners(),this._setInstances(),this._emitInitEvent(),this.options.scrollToAnchor)var o=setInterval(function(){"complete"===document.readyState&&(clearInterval(o),document.getElementById(t.dataset.id).scrollIntoView())},300)}return _createClass(e,[{key:"addOnSubmitCallback",value:function(e){"function"==typeof e&&this._beforeSubmitCallbackStack.push(e)}},{key:"addOnSuccessfulAjaxSubmit",value:function(e){"function"==typeof e&&this._successfulAjaxSubmitCallbackStack.push(e)}},{key:"addOnFailedAjaxSubmit",value:function(e){"function"==typeof e&&this._failedAjaxSubmitCallbackStack.push(e)}},{key:"addOnAfterAjaxSubmit",value:function(e){"function"==typeof e&&this._afterAjaxSubmitCallbackStack.push(e)}}]),e}();_defineProperty(Freeform,"_BACK_BUTTON_NAME","form_previous_page_button"),_defineProperty(Freeform,"instances",new WeakMap),_defineProperty(Freeform,"getInstance",function(e){return Freeform.instances.get(e)}),Element.prototype.remove=function(){this.parentElement.removeChild(this)},NodeList.prototype.remove=HTMLCollection.prototype.remove=function(){for(var e=this.length-1;e>=0;e--)this[e]&&this[e].parentElement&&this[e].parentElement.removeChild(this[e])},window.NodeList&&!NodeList.prototype.forEach&&(NodeList.prototype.forEach=function(e,t){t=t||window;for(var r=0;r<this.length;r++)e.call(t,this[r],r,this)});