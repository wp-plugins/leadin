var total_contacts = 0;
var progress_bar_step_value = 0;
var total_contacts_migrated = 0;
var ajax_avg_time = 0;
var ajax_response_seconds = 0;
var contacts_for_ajax_avg = 20;
var completedMigration = false;

jQuery(document).ready(function ($) {
  leadinStartSettingsMigration();
});

function leadinStartSettingsMigration() {
  jQuery.ajax({
    type: 'POST',
    url: li_ajax.ajax_url,
    data: {
      "action": "leadin_migrate_settings"
    },
    success: function (data) {
      leadinStartContactsMigration();
    },
    error: function (error_data) {
      alert(error_data);
    }
  });
}

function leadinMigrateEspSyncs() {
  jQuery.ajax({
    type: 'POST',
    url: li_ajax.ajax_url,
    data: {
      "action": "leadin_migrate_esp_syncs"
    },
    success: function (data) {

      setTimeout(function () {
        $('#main-content').html(
          "<h1>Step 2 of 2: Finishing the migration</h1>" +
          "<p>Your migration is nearly complete. We're processing all your contacts on our servers now and we'll send you an email when it's done.</p><p>You can close this tab whenever you'd like.</p>"
        );

        $('#progress-text').text("Done uploading " + total_contacts_migrated + " contacts.");
      }, 750);

      window.removeEventListener("beforeunload", closePageDialogue);
    },
    error: function (error_data) {
      //alert(error_data);
    }
  });
}

function leadinStartContactsMigration() {
  jQuery.ajax({
    type: 'POST',
    url: li_ajax.ajax_url,
    data: {
      "action": "leadin_echo_contacts_for_migration"
    },
    success: function (data) {

      if (data == 'migration complete' || data == 'no contacts') {
        $('#main-content').html(
          "<h1>Step 2 of 2: Finishing the migration</h1>" +
          "<p>Your migration is nearly complete. We're processing all your contacts on our servers now and we'll send you an email when it's done.</p><p>You can close this tab whenever you'd like.</p>"
        );

        $('#leadin-migration-progress-bar').css('width', '100%');
        $('#progress-text').text("All your contacts have been uploaded to our servers.");
        leadinSetMigrationCompleteOption();
        window.removeEventListener("beforeunload", closePageDialogue);

      } else {
        var contacts = $.parseJSON(data);
        if (contacts.length) {
          total_contacts = contacts.length;
          progress_bar_step_value = 1 / total_contacts;

          leadinContactMigrationWorker(contacts);

        }
      }

    },
    error: function (error_data) {
      //alert(error_data);
    }
  });
}

function leadinContactMigrationWorker(contactArray) {

  if (contactArray.length === 0) {
    if (!completedMigration) {
      leadinMigrateEspSyncs();
      leadinFireCompleteMigrationEvent();
      completedMigration = true;
    }
    return false
  } else {
    var nextContact = contactArray.pop();
    jQuery.ajax({
      type: 'POST',
      url: li_ajax.ajax_url,
      data: {
        "action": "leadin_migrate_contact",
        "hashkey": nextContact.hashkey
      },
      success: function (data) {
        leadinIncrementContact();
      },
      error: function (errorData) {
        console.error("Failed to migrate contact with hashkey " + nextContact.hashkey);
        leadinIncrementContact();
      },
      complete: function () {
        setTimeout(leadinContactMigrationWorker(contactArray), 500);
      }
    });
  }

}

function leadinIncrementContact() {
  if (!total_contacts_migrated)
  {
    document.title = "Uploading your contacts...";
    $('#progress-text').text("Uploading your contacts...");
  }
  
  total_contacts_migrated++;

  var progressBarWidth = (total_contacts_migrated * progress_bar_step_value) * 100;

  $('#leadin-migration-progress-bar').css('width', progressBarWidth + '%');

  if ( total_contacts_migrated )
  {
    document.title = "(" + total_contacts_migrated + '/' + total_contacts + ") contacts uploaded...";
    $('#progress-text').text("Uploading your contacts... (" + total_contacts_migrated + '/' + total_contacts + ")");
  }

}

function leadinFireCompleteMigrationEvent() {
  jQuery.ajax({
    type: 'POST',
    url: li_ajax.ajax_url,
    data: {
      "action": "leadin_set_migration_complete_flag"
    },
    success: function (data) {
      // Hurray!
    },
    error: function (data) {
      console.error("Unable to set migration complete flag: " + data);
    }
  })
}

function leadinSetMigrationCompleteOption() {
  jQuery.ajax({
    type: 'POST',
    url: li_ajax.ajax_url,
    data: {
      "action": "leadin_set_migration_complete_option"
    },
    success: function (data) {
      // Hurray!
    },
    error: function (data) {
      console.error("Unable to set migration complete option: " + data);
    }
  })

}

var closePageDialogue = function (e) {
  var confirmationMessage = 'Leadin is currently migrating your contacts to our cloud servers. ';
  confirmationMessage += 'If you close this page, the migration will pause and not finish uploading your contacts.';

  (e || window.event).returnValue = confirmationMessage; //Gecko + IE
  return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
};

window.onload = function () {
  window.addEventListener("beforeunload", closePageDialogue);
};