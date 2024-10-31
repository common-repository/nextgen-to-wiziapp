jQuery(document).ready(function() {
		Nextgen_To_Wiziapp.progress_bar_container = jQuery("#nw_progress_bar_container"),
		Nextgen_To_Wiziapp.show_error_container   = jQuery("#nextgen_to_wiziapp_warning"),

		Nextgen_To_Wiziapp.ajax_setup();

		jQuery("#nextgen_to_wiziapp_scan input").click(function() {
				Nextgen_To_Wiziapp.progress_bar_container.show(1, Nextgen_To_Wiziapp.processing);
		});
		jQuery("#nextgen_to_wiziapp_remove input").click(Nextgen_To_Wiziapp.hide_message);
});

Nextgen_To_Wiziapp = {
	ajax_setup: function() {
		jQuery.ajaxSetup({
				timeout: 60*1000,
				error: function(req, error) {
					Nextgen_To_Wiziapp.show_error("Connection problem: " + error + ", status: " + req.status + ".");
				}
		});
	},
	processing: function() {
		var params = {
			action: 'nextgen_to_wiziapp_scanning',
		};
		jQuery.post(ajaxurl, params, Nextgen_To_Wiziapp.get_json, 'text');
	},
	get_json: function(incoming_data) {
		var pattern = /nextgen_to_wiziapp_start(.+)nextgen_to_wiziapp_end/m;
		var result = pattern.exec(incoming_data);
		var data = jQuery.parseJSON(result[1]);
		Nextgen_To_Wiziapp.view_process(data);
	},
	view_process: function(data) {
		if (data.success === 1) {
			jQuery("#nw_progress_bar").css('width', data.percent + '%');

			if (data.percent < 100) {
				Nextgen_To_Wiziapp.processing();
			} else {
				jQuery("#nextgen_to_wiziapp_message").remove();
			}
		} else {
			Nextgen_To_Wiziapp.show_error(data.error_message);
		}
	},
	hide_message: function() {
		jQuery("#nextgen_to_wiziapp_message").remove();

		var params = {
			action: 'nextgen_to_wiziapp_message'
		};
		jQuery.post(ajaxurl, params);
	},
	show_error: function(warning) {
		warning = warning + " Try again, or click \"Hide..\" button."

		Nextgen_To_Wiziapp.progress_bar_container.hide();
		Nextgen_To_Wiziapp.show_error_container
		.children("p")
		.remove();

		Nextgen_To_Wiziapp.show_error_container
		.show()
		.children("input")
		.click(function() {
				Nextgen_To_Wiziapp.show_error_container.hide();
				Nextgen_To_Wiziapp.progress_bar_container.show(1, Nextgen_To_Wiziapp.processing);
		})
		.before("<p>" + warning + "</p>");
	},
}