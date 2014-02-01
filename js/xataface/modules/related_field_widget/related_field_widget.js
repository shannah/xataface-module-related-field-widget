//require <xatajax.form.core.js>
//require <xataface.io.js>
(function(){
    var $ = jQuery;
    var data = Xataface_modules_related_field_widget_metadata;
    
    var saveQueue = {};
    
    function findRelatedFields(form){
        var out = [];
        $('[data-xf-field]', form).each(function(){
            var fieldName = $(this).attr('data-xf-field');
            if ( data[fieldName] ){
                out.push(this);
            }
        });
        
        return out;
    }
    
    function getFieldInfo(field){
        var fieldName = $(field).attr('data-xf-field');
        var info = data[fieldName];
        return info;
    }
    
    function getLookupField(field){
        
        var info = getFieldInfo(field);
        if ( !info ){
            return;
        }
        
        var lookupField = XataJax.form.findField(field, info.lookup_field);
        return lookupField;
    }
    
    function initRelatedField(field){
        var lookupField = getLookupField(field);
        if ( !lookupField ){
            // Could not find lookup field
            return;
        }
        $(field).data('lookup_field', lookupField);
        // Register change event on lookupField
        $(lookupField).change(function(){
            updateRelatedField(field);
        });
        
        $(field).change(function(){
            var prevValue = $(field).data('prev_value');
            if ( $(field).val() !== prevValue ){
                $(field).data('dirty', true);
            } else {
                $(field).data('dirty', false);
            }
            
        });
        
        $(field).data('prev_value', $(field).val());
    }
    
    function updateRelatedField(field){
        var lookupField = $(field).data("lookup_field");
        if ( !lookupField ){
            return;
        }
        
        var prevLookupValue = $(field).data('prev_lookup_value');
        var newLookupValue = $(lookupField).val();
        
        var isDirty = $(field).data('dirty');
        
        var info = getFieldInfo(field);
        if ( isDirty){
            saveField(field).then(function(){
                loadFieldData(field);
            });
            
            
            
        } else {
            loadFieldData(field);
        }
        
    }
    
    
    
    function saveField(field){
        var callback = function(){};
        var out = {
            then : function(cb){
                callback = cb;
            }
        };
        
        queueSave(field).then(function(){
            callback();
        });
        return out;
    }
        
       
    function queueSave(field){
        var callback = function(){};
        var out = {
            then : function(cb){
                callback = cb;
            }
        };
        
        
        var info = getFieldInfo(field);
        var queueId = info['lookup_field']+'#'+$(field).data('prev_lookup_value');
        if ( !saveQueue[queueId] ){
            saveQueue[queueId] = [];
        }
        var queue = saveQueue[queueId];
        queue.push(field);
        
        var timer = setTimeout(function(){
            if ( !saveQueue[queueId] ){
                callback();
                return;
            }
            var queue = saveQueue[queueId];
            var endQueue = [];
            delete saveQueue[queueId];
            var vals = {};
            while ( queue.length > 0 ){
                var fld = queue.shift();
                endQueue.push(fld);
                vals[info['name']] = $(fld).val();
            }
            
            var jsonVals = JSON.stringify(vals);
            var q = {
                '-action' : 'related_field_widget_update_values',
                '-table' : info['current_table'],
                '--lookup_field' : info['lookup_field'],
                '--vals' : jsonVals
            };
            $.post(DATAFACE_SITE_HREF, q, function(res){
                if ( res && res.code == 200 ){
                    callback();
                } else {
                    
                    var message = "Failed to save due to server error.";
                    if ( res && res.message ){
                        message = res.message;
                    }
                    promptRetrySave(message).then(function(){
                        while ( endQueue.length > 0 ){
                    if ( !saveQueue[queueId] ){
                        saveQueue[queueId] = [];
                    }
                    saveQueue[queueId] = endQueue.shift();
                }
                    });
                }
            });
            
        }, 100);
        
        return out;
        
        var info = getFieldInfo(field);
        var prevLookupValue = $(field).data('prev_lookup_value');
        if ( info['autosave'] ){
                
            var q = {
                '-action' : 'related_field_widget_save_field',
                '-table' : info['current_table'],
                '-field' : info['name'],
                '--value' : $(field).val(),
                '--lookupValue' : prevLookupValue
            };
            $.post(DATAFACE_SITE_HREF, q, function(res){
                if ( res && res.code === 200 ){
                    callback();
                } else {
                    var message = res && res.message ? res.message : 'Failed to save field due to a server error.';
                    promptRetrySave(message).then(function(){
                        saveField(field);
                    });
                }
            });
        }
        
        
        return then;
    }
    
})();