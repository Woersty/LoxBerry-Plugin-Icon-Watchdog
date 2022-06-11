/*!
 * Bootstrap Timepicker
 * @author Amir Hussain
**/
var bstptid = '';
!function (t, i) {
	"function" == typeof define && define.amd ? define(["jquery"], i) : "object" == typeof exports ? module.exports = i(require("jquery")) : t.$ = i(t.jQuery)
}(this, function (t) {
	"use strict";
	var i = function (t) {
			return t >= 10 ? t + "" : "0" + t
		}, e = /^[0-9]{1,2}:[0-9]{1,2}$/, s = {}, n = function () {
		}, o = new Array(24).fill(null).map(function (t, e) {
			var s = i(e);
			return '<li class="cell-2 js-hour-cell" data-val="' + s + '">' + s + "</li>"
		}).join(""), c = new Array(4).fill(null).map(function (t, e) {
			var s = i(15 * e);
			return '<li class="cell-2 js-minute-cell" data-val="' + s + '">' + s + "</li>"
		}).join(""),
		l = t('<div class="timepicker">\t\t<div v-show class="title">'+timepicker_lang[0]+'</div><center><div class="timepickerOK" onclick="$(body).trigger(\'click\');"></div></center>\t\t\t<div class="chose-all">\t\t\t\t<div class="handle">\t\t\t\t\t<div class="cell-4"><a class="icon-up js-plus-houer"></a></div>\t\t\t\t\t<div class="cell-2"></div>\t\t\t\t\t<div class="cell-4"><a class="icon-up js-plus-minute"></a></div>\t\t\t\t</div>\t\t\t\t<div class="text">\t\t\t\t\t<div class="cell-4"><a class="js-hour-show" title="'+timepicker_lang[1]+'"></a></div>\t\t\t\t\t<div class="cell-2">:</div>\t\t\t\t\t<div class="cell-4"><a class="js-minute-show" title="'+timepicker_lang[2]+'"></a></div>\t\t\t\t</div>\t\t\t\t<div class="handle">\t\t\t\t\t<div class="cell-4"><a class="icon-down js-minus-houer"></a></div>\t\t\t\t\t<div class="cell-2"></div>\t\t\t\t\t<div class="cell-4"><a class="icon-down js-minus-minute"></a></div>\t\t\t\t</div>\t\t\t</div>\t\t\t<div class="chose-hour">\t\t\t\t<ul class="handle">' + o + '</ul>\t\t\t</div>\t\t\t<div class="chose-minute">\t\t\t\t<ul class="handle">' + c + "</ul>\t\t\t</div>\t\t</div>\t</div>");
	return l.find("a").attr("href", "javascript:void(0);"), s.content = l, s.title = l.find(".title"), s.choseAll = l.find(".chose-all"), s.choseMinute = l.find(".chose-minute"), s.choseHour = l.find(".chose-hour"), s.hourShow = l.find(".js-hour-show"), s.minuteShow = l.find(".js-minute-show"), s.update = function () {
		return bstptid.val(i(this.hour) + ":" + i(this.minute)), this.minuteShow.text(i(this.minute)), this.hourShow.text(i(this.hour)), this.inputTarget.$timepickerUpdate(), this
	}, s.bindEvent = function () {
		var t = this;
		t.hasBind || (t.hasBind = !0, this.content.on("click", ".js-minus-minute", function () {
			t.minute <= 14 ? t.hour-- : t.hour=t.hour;
			t.hour < 0 ? t.hour=23 : t.hour=t.hour;
			t.minute <= 14 ? t.minute = 45 : t.minute=t.minute-15, t.update()
		}).on("click", ".js-plus-minute", function () {
			t.minute >= 45 ? t.hour++ : t.hour=t.hour;
			t.hour > 23 ? t.hour=0 : t.hour=t.hour;
			t.minute >= 45 ? t.minute = 0 : t.minute=t.minute+15, t.update()
		}).on("click", ".js-plus-houer", function () {
			t.hour >= 23 ? t.hour = 0 : t.hour++, t.update()
		}).on("click", ".js-minus-houer", function () {
			t.hour <= 0 ? t.hour = 23 : t.hour--, t.update()
		}).on("click", ".js-minute-cell", function () {
			t.minute = +this.getAttribute("data-val"), t.update(), t.choseMinute.hide(), t.choseAll.show(), t.title.text(timepicker_lang[0])
		}).on("click", ".js-hour-cell", function () {
			t.hour = +this.getAttribute("data-val"), t.update(), t.choseHour.hide(), t.choseAll.show(), t.title.text(timepicker_lang[0])
		}).on("click", function (t) {
			t.stopPropagation()
		}), t.hourShow.on("click", function () {
			t.choseAll.hide(), t.choseHour.show(), t.title.text(timepicker_lang[3])
		}), t.minuteShow.on("click", function () {
			t.choseAll.hide(), t.choseMinute.show(), t.title.text(timepicker_lang[4])
		}))
	}, t.timepicker = s, t.fn.timepicker = function (i) {
		var s, o, c = this, l = t.timepicker, u = t("html");
		if (this[0].nodeName && "INPUT" === this[0].nodeName) return this.$timepickerUpdate = n, this.off("click").on("click", function (i) {
			var n = this.value;
			bstptid = $(this);
			e.test(n) ? (n = n.split(":"), s = +n[0], o = +n[1]) : (n = new Date, s = n.getHours(), o = n.getMinutes());
			if ($(this).closest('table').length > 0) {
				var pos = $(this).offset();
				var h = pos.left + "px", a = pos.top + this.offsetHeight + "px";
			} else {
				var h = this.offsetLeft + "px", a = this.offsetTop + this.offsetHeight + "px";
			}
			l.inputTarget = c, l.content.appendTo(this.offsetParent).css({
				left: h,
				top: a
			}), l.hour = s, l.minute = o, l.choseAll.show(), l.choseHour.hide(), l.choseMinute.hide(), l.update(), t.timepicker.bindEvent(), i.stopPropagation(), u.one("click", function () {
				l.content.off().remove(), l.hasBind = !1;
				save_config();
			})
		}), this.off("keydown").on("keydown", function () {
			return !1
		}), this.update = function (i) {
			t.isFunction(i) ? this.$timepickerUpdate = i : this.$timepickerUpdate = n;
		}, this
	}, t
});
