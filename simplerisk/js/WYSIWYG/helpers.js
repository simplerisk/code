// A set of WYSIWYG editor related helper functions that can be used both in the core and the extras

// destroy an editor by its id
function destroy_editor(id) {
    hugerte.get(id).destroy();
}

/**
 * Destroy all active editors on the page
 */
function destroy_all_editors() {
    hugerte.remove();
}

/**
 * Force save the edited data for all active editor instances
 * into the HTML elements the editor was instantiated on
 */
function force_save_all_editors() {
    hugerte.triggerSave();
}

/**
 * Update an editor's content
 * 
 * id: the editor's ID
 * content: new content to be set
 *  
 */
function setEditorContent(id, content) {
    hugerte.get(id).setContent(content);
}
