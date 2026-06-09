YUI.add('moodle-availability_pointbadges-form', function (Y, NAME) {

M.availability_pointbadges = M.availability_pointbadges || {};
M.availability_pointbadges.form = Y.Object(M.core_availability.plugin);

M.availability_pointbadges.form.initInner = function() {
};

M.availability_pointbadges.form.getNode = function(json) {
    var html = '<label><span class="pe-3">' + M.util.get_string('title', 'availability_pointbadges') + '</span> ' +
            '<span class="availability-group">' +
            '<select name="restriction" class="form-select d-inline-block" style="width: auto;">' +
            '<option value="premium">' + M.util.get_string('restriction_premium', 'availability_pointbadges') + '</option>' +
            '<option value="vip">' + M.util.get_string('restriction_vip', 'availability_pointbadges') + '</option>' +
            '</select></span></label>';
    var node = Y.Node.create('<span class="d-flex flex-wrap align-items-center">' + html + '</span>');

    if (json.restriction !== undefined) {
        node.one('select[name=restriction]').set('value', json.restriction);
    }

    if (!M.availability_pointbadges.form.addedEvents) {
        M.availability_pointbadges.form.addedEvents = true;
        var root = Y.one('.availability-field');
        root.delegate('change', function() {
            M.core_availability.form.update();
        }, '.availability_pointbadges select[name=restriction]');
    }

    return node;
};

M.availability_pointbadges.form.fillValue = function(value, node) {
    value.restriction = node.one('select[name=restriction]').get('value');
};

M.availability_pointbadges.form.fillErrors = function(errors, node) {
    var restriction = node.one('select[name=restriction]').get('value');
    if (restriction !== 'premium' && restriction !== 'vip') {
        errors.push('availability_pointbadges:missing');
    }
};

}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
