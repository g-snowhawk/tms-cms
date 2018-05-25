/**
 * Javascript Library for Tak-Me CMS
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @copyright 2017 PlusFive (https://www.plus-5.com)
 * @version 1.0.0
 */
var TM_Preview = function() {
    this.onLoad(this, 'init');
};
TM_Preview.prototype.init = function(ev) {
    var i, el;
    el = document.getElementsByTagName('a');
    for(i = 0; i < el.length; i++){
        el[i].addEventListener('click', this.trigger, false);
    }
    el = document.getElementsByTagName('form');
    for(i = 0; i < el.length; i++){
        el[i].addEventListener('submit', this.trigger, false);
    }
    window.addEventListener('beforeunload', this.trigger, false);
};
TM_Preview.prototype.trigger = function(ev) {
    ev.preventDefault();
};
TM_Preview.prototype.onLoad = function(scope, func) {
    addEventListener('load', function(ev){ scope[func](ev); }, false);
};
// Create instance
if(!window.TM) window.TM = new TM_Common();
TM.preview = new TM_Preview();
