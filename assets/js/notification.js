(function ($) {
  "use strict";

  $(document).ready(function () {
    if (typeof cartContactNotificationData === "undefined") {
      return;
    }

    const config = cartContactNotificationData;

    if (config.enabled !== "1" || window.location.pathname.includes("/cart")) {
      return;
    }

    const storageKeys = {
      lastShown: "cart_contact_notification_last_shown",
      cartCreated: "cart_contact_notification_cart_created_time",
      sent: "cart_contact_notification_sent",
    };

    let notificationShown = false;
    let modalCreated = false;

    async function checkCart() {
      try {
        const data = await window.checkCart42();

        if (
          data &&
          data.items_count > 0 &&
          localStorage.getItem(storageKeys.sent) !== "1"
        ) {
          showCartNotification();
        }
      } catch (error) {
        console.error("Cart notification error:", error);
      }
    }

    function showCartNotification() {
      if (notificationShown || !canShowByInterval()) {
        return;
      }

      // const notification = $(
      //   '<button type="button" class="ccn-toast">' +
      //     '<span class="ccn-toast__text"></span>' +
      //     '<span class="ccn-toast__action"></span>' +
      //     '<span class="ccn-toast__close" aria-hidden="true">×</span>' +
      //   "</button>",
      // );

      // notification.find(".ccn-toast__text").text(config.texts.notification);
      // notification.find(".ccn-toast__action").text(config.texts.notificationAction);

      // notification.on("click", function (event) {
      //   if ($(event.target).hasClass("ccn-toast__close")) {
      //     notification.remove();
      //     return;
      //   }

      //   openModal();
      // });

      // $("body").append(notification);

      const notification = $(
        '<button type="button" class="ccn-toast">' +
          '<span class="ccn-toast__text"></span>' +
          '<span class="ccn-toast__action"></span>' +
          "</button>",
      );

      notification.find(".ccn-toast__text").text(config.texts.notification);
      notification
        .find(".ccn-toast__action")
        .text(config.texts.notificationAction);

      const notification2 = `
      <button type="button" class="ccn-toast">
      <span class="ccn-toast__text">${config.texts.notification}</span>
      <span class="ccn-toast__action">${config.texts.notificationAction}</span>
      `;

      Toastify({
        text: notification2,
        duration: -1,
        gravity: "top",
        position: "right",
        close: true,
        style: {
          background: "rgba(0, 0, 0, 0.7)",
          borderRadius: "7px",
          padding: "12px 20px",
        },
        escapeMarkup: false, // Разрешаем HTML
        onClick: function () {
          openModal();
        },
      }).showToast();

      localStorage.setItem(storageKeys.lastShown, Date.now().toString());
      notificationShown = true;
    }

    function canShowByInterval() {
      const intervalMs = parseFloat(config.intervalHours) * 60 * 60 * 1000;
      const now = Date.now();
      const timestamps = [
        localStorage.getItem(storageKeys.lastShown),
        localStorage.getItem(storageKeys.cartCreated),
      ].filter(Boolean);

      return timestamps.every(function (timestamp) {
        return now - parseInt(timestamp, 10) >= intervalMs;
      });
    }

    function openModal() {
      ensureModal();
      $(".ccn-modal").addClass("ccn-modal--open");
      $(".ccn-modal__phone").trigger("focus");
    }

    function closeModal() {
      $(".ccn-modal").removeClass("ccn-modal--open");
    }

    function ensureModal() {
      if (modalCreated) {
        return;
      }

      const methodsHtml = Object.keys(config.contactMethods)
        .map(function (key, index) {
          const checked = index === 0 ? " checked" : "";
          return (
            '<label class="ccn-modal__method">' +
            '<input type="radio" name="ccn_contact_method" value="' +
            escapeHtml(key) +
            '"' +
            checked +
            ">" +
            "<span>" +
            escapeHtml(config.contactMethods[key]) +
            "</span>" +
            "</label>"
          );
        })
        .join("");
      // '<button type="button" class="ccn-modal__close" aria-label="Close">×</button>' +
      const modal = $(
        '<div class="ccn-modal" role="dialog" aria-modal="true">' +
          '<div class="ccn-modal__backdrop"></div>' +
          '<form class="ccn-modal__panel">' +
          '<button type="button" class="ccn-modal__close" aria-label="Close">×</button>' +
          '<h2 class="ccn-modal__title"></h2>' +
          '<label class="ccn-modal__label">' +
          '<span class="ccn-modal__phone-label"></span>' +
          '<input class="ccn-modal__phone" type="tel" name="phone" required autocomplete="tel">' +
          "</label>" +
          '<fieldset class="ccn-modal__fieldset">' +
          '<legend class="ccn-modal__contact-label"></legend>' +
          '<div class="ccn-modal__methods">' +
          methodsHtml +
          "</div>" +
          "</fieldset>" +
          '<div class="ccn-modal__status" aria-live="polite"></div>' +
          '<div class="ccn-modal__actions">' +
          '<button type="button" class="ccn-modal__cancel"></button>' +
          '<button type="submit" class="ccn-modal__submit"></button>' +
          "</div>" +
          "</form>" +
          "</div>",
      );

      modal.find(".ccn-modal__title").text(config.texts.modalTitle);
      modal.find(".ccn-modal__phone-label").text(config.texts.phoneLabel);
      modal.find(".ccn-modal__contact-label").text(config.texts.contactLabel);
      modal.find(".ccn-modal__cancel").text(config.texts.cancel);
      modal.find(".ccn-modal__submit").text(config.texts.submit);

      modal.on(
        "click",
        ".ccn-modal__backdrop, .ccn-modal__close, .ccn-modal__cancel",
        closeModal,
      );
      modal.on("submit", submitForm);

      $("body").append(modal);
      modalCreated = true;
    }

    function submitForm(event) {
      event.preventDefault();

      const form = $(event.currentTarget);
      const submit = form.find(".ccn-modal__submit");
      const status = form.find(".ccn-modal__status");
      const phone = form.find('[name="phone"]').val();
      const contactMethod = form
        .find('[name="ccn_contact_method"]:checked')
        .val();

      submit.prop("disabled", true);
      status.removeClass("ccn-modal__status--error").text("");

      $.ajax({
        url: config.ajaxUrl,
        method: "POST",
        dataType: "json",
        data: {
          action: "cart_contact_notification_send",
          nonce: config.nonce,
          phone: phone,
          contact_method: contactMethod,
        },
      })
        .done(function () {
          localStorage.setItem(storageKeys.sent, "1");
          status.text(config.texts.success);
          setTimeout(function () {
            closeModal();
            // $(".ccn-toast").remove();
          }, 1200);
        })
        .fail(function () {
          status.addClass("ccn-modal__status--error").text(config.texts.error);
        })
        .always(function () {
          submit.prop("disabled", false);
        });
    }

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    $(document.body).on("added_to_cart", function () {
      if (!localStorage.getItem(storageKeys.cartCreated)) {
        localStorage.setItem(storageKeys.cartCreated, Date.now().toString());
      }

      localStorage.removeItem(storageKeys.sent);
    });

    setTimeout(checkCart, 500);
  });
})(jQuery);
