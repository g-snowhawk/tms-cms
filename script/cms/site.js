/**
 * Javascript Library for Tak-Me CMS
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2017 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */

var TM_Site = function() {
    this.onLoad(this, 'init');
};

TM_Site.prototype.onLoad = function(scope, func) {
    addEventListener('load', function(e){ scope[func](e); }, false);
}

TM_Site.prototype.init = function() {
    var rd = document.getElementsByTagName('input');
    for(var i = 0; i < rd.length; i++){
        var element = rd[i];
        if(element.name === 'choice'){
            element.addEventListener('click', this.siteSelection, false);
        }
    }
};

TM_Site.prototype.siteSelection = function(evn) {
    var element = evn.target;
    if(element.checked) {
        element.form.submit();
    }
}

// Create instance
if(!window.TM) window.TM = new TM_Common();
TM.site = new TM_Site();
