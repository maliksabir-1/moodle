YUI.add('moodle-availability_level-form', function (Y, NAME) {

M.availability_level = M.availability_level || {};
M.availability_level.form = Y.Object(M.core_availability.plugin);

M.availability_level.form.initInner = function() {
};

M.availability_level.form.getNode = function(json) {
    var html = '<label><span class="pe-3">' + M.util.get_string('title', 'availability_level') + '</span> ' +
            '<span class="availability-group">' +
            '<input type="number" name="level" class="form-control d-inline-block" style="width: 6em;" value="1" min="1"/>' +
            '</span></label>';
    var node = Y.Node.create('<span class="d-flex flex-wrap align-items-center">' + html + '</span>');

    if (json.level !== undefined) {
        node.one('input[name=level]').set('value', json.level);
    }

    if (!M.availability_level.form.addedEvents) {
        M.availability_level.form.addedEvents = true;
        var root = Y.one('.availability-field');
        root.delegate('change', function() {
            M.core_availability.form.update();
        }, '.availability_level input[name=level]');
    }

    return node;
};

M.availability_level.form.fillValue = function(value, node) {
    value.level = parseInt(node.one('input[name=level]').get('value'), 10);
};

M.availability_level.form.fillErrors = function(errors, node) {
    var level = parseInt(node.one('input[name=level]').get('value'), 10);
    if (isNaN(level) || level < 1) {
        errors.push('availability_level:error_invalidlevel');
    }
};

}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
