/**
 * editor.js - Professional Code Editor with Advanced File Explorer
 * Version: 2.7.0 - Complete rewrite of File Explorer
 */

(function() {
    'use strict';

    // ============================================
    // State Management
    // ============================================

    const state = {
        tabs: [],
        activeTabIndex: -1,
        nextTabId: 1,
        isSaving: false,
        currentDir: '',
        editorInitialized: false,
        searchQuery: '',
        isSearching: false,
        expandedFolders: new Set(),
        isLoading: false,
        fileTreeData: []
    };

    // ============================================
    // DOM References
    // ============================================

    const DOM = {
        tabsContainer: document.getElementById('tabsContainer'),
        editorWrapper: document.getElementById('editorWrapper'),
        editorPlaceholder: document.getElementById('editorPlaceholder'),
        codeEditor: document.getElementById('codeEditor'),
        fileTreeContent: document.getElementById('fileTreeContent'),
        statusInfo: document.getElementById('statusInfo'),
        statusCursor: document.getElementById('statusCursor'),
        statusMode: document.getElementById('statusMode'),
        statusEncoding: document.getElementById('statusEncoding'),
        fileSearch: document.getElementById('fileSearch'),
        searchCount: document.getElementById('searchCount'),
        searchClear: document.querySelector('.search-clear'),
        currentDir: document.getElementById('currentDir')
    };

    // ============================================
    // CodeMirror Editor Instance
    // ============================================

    let editor = null;

    function initEditor() {
        if (editor && state.editorInitialized) return;

        try {
            DOM.codeEditor.style.display = 'none';
            
            editor = CodeMirror.fromTextArea(DOM.codeEditor, {
                lineNumbers: true,
                matchBrackets: true,
                styleActiveLine: true,
                indentUnit: 4,
                tabSize: 4,
                indentWithTabs: false,
                lineWrapping: false,
                autoCloseBrackets: true,
                autoCloseTags: true,
                theme: 'default',
                value: '',
                extraKeys: {
                    'Ctrl-S': function() { saveCurrentFile(); return false; },
                    'Cmd-S': function() { saveCurrentFile(); return false; },
                    'Ctrl-W': function() { closeCurrentTab(); return false; },
                    'Cmd-W': function() { closeCurrentTab(); return false; },
                    'Ctrl-N': function() { openNewFile(); return false; },
                    'Cmd-N': function() { openNewFile(); return false; },
                    'Tab': function(cm) {
                        if (cm.somethingSelected()) {
                            cm.indentSelection('add');
                        } else {
                            cm.replaceSelection('    ', 'end');
                        }
                    }
                }
            });

            state.editorInitialized = true;

            editor.on('cursorActivity', function() { updateStatusBar(); });
            editor.on('change', function() { markCurrentTabUnsaved(true); });

            updateStatusBar();
            DOM.statusMode.textContent = 'Plain Text';
            DOM.codeEditor.style.display = 'none';
            
            setTimeout(function() { editor.refresh(); }, 200);
            
            console.log('✅ CodeMirror initialized');
        } catch (e) {
            console.error('Error initializing CodeMirror:', e);
            showToast('Error initializing editor', 'error');
        }
    }

    function updateStatusBar() {
        if (!editor) return;
        try {
            const cursor = editor.getCursor();
            DOM.statusCursor.textContent = 'Ln ' + (cursor.line + 1) + ', Col ' + (cursor.ch + 1);
        } catch (e) {}
    }

    // ============================================
    // Utility Functions
    // ============================================

    function normalizePath(filepath) {
        if (!filepath) return '';
        return filepath.replace(/\\/g, '/');
    }

    function getFileExtension(filename) {
        return filename.split('.').pop().toLowerCase();
    }

    /**
     * Get CodeMirror mode from file extension
     * 🔥 FIX: Added more modes and proper mapping
     */
    function getModeFromExtension(filename) {
        var ext = getFileExtension(filename);
        
        // CodeMirror mode mapping
        var modes = {
            // Web languages
            'php': 'text/x-php',
            'html': 'text/html',
            'htm': 'text/html',
            'css': 'text/css',
            'js': 'text/javascript',
            'javascript': 'text/javascript',
            'json': 'application/json',
            'xml': 'application/xml',
            'svg': 'application/xml',
            
            // Data formats
            'sql': 'text/x-sql',
            'yaml': 'text/x-yaml',
            'yml': 'text/x-yaml',
            'toml': 'text/x-toml',
            'ini': 'text/x-ini',
            'cfg': 'text/x-ini',
            'conf': 'text/x-ini',
            
            // Markup
            'md': 'text/markdown',
            'markdown': 'text/markdown',
            'rst': 'text/x-rst',
            
            // Scripting
            'py': 'text/x-python',
            'python': 'text/x-python',
            'sh': 'text/x-sh',
            'bash': 'text/x-sh',
            'bat': 'text/x-dosbatch',
            'cmd': 'text/x-dosbatch',
            'ps1': 'text/x-powershell',
            
            // Other
            'txt': 'text/plain',
            'log': 'text/plain',
            'csv': 'text/plain',
            'tsv': 'text/plain'
        };
        
        var mode = modes[ext] || 'text/plain';
        console.log('🔍 Detected mode for .' + ext + ' → ' + mode);
        return mode;
    }

    function getFileIcon(filename) {
        var ext = getFileExtension(filename);
        var icons = {
            'php': '🐘', 'html': '🌐', 'htm': '🌐', 'css': '🎨',
            'js': '⚡', 'json': '📋', 'xml': '📋', 'sql': '🗄️',
            'md': '📝', 'py': '🐍', 'sh': '💻', 'bat': '💻', 'txt': '📄'
        };
        return icons[ext] || '📄';
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function showToast(message, type) {
        type = type || 'info';
        
        var container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'toast ' + type;
        
        var iconMap = {
            'success': '✅', 'error': '❌', 'warning': '⚠️', 'info': 'ℹ️'
        };
        
        toast.innerHTML = `
            <span class="toast-icon">${iconMap[type] || 'ℹ️'}</span>
            <span class="toast-message">${message}</span>
            <span class="toast-close">✕</span>
        `;

        container.appendChild(toast);

        var timeout = setTimeout(function() {
            removeToast(toast);
        }, 3000);

        toast.querySelector('.toast-close').addEventListener('click', function() {
            clearTimeout(timeout);
            removeToast(toast);
        });

        function removeToast(el) {
            el.classList.add('hide');
            setTimeout(function() {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
                if (container && container.children.length === 0) {
                    container.remove();
                }
            }, 300);
        }
    }

    // ============================================
    // 🔥 CORE: File Explorer Functions
    // ============================================

    /**
     * Render the file tree from data
     */
    function renderFileTree(files) {
        if (!DOM.fileTreeContent) return;

        if (!files || files.length === 0) {
            DOM.fileTreeContent.innerHTML = `
                <div class="file-tree-empty">
                    <span style="font-size: 32px; display: block; margin-bottom: 10px;">📁</span>
                    <div style="color: #666; font-weight: 500;">Empty directory</div>
                    <div style="color: #444; font-size: 11px; margin-top: 4px;">No files or folders found</div>
                </div>
            `;
            return;
        }

        var html = '<ul class="file-tree">';
        
        files.forEach(function(file) {
            var icon = file.is_dir ? '📁' : getFileIcon(file.name);
            var jsPath = file.js_path || file.path.replace(/\\/g, '/');
            var sizeStr = file.size_formatted || formatFileSize(file.size);
            var isEditable = file.editable !== undefined ? file.editable : true;
            
            if (file.is_dir) {
                html += `
                    <li class="folder" data-path="${jsPath}">
                        <div class="file-item">
                            <span class="folder-toggle" onclick="toggleFolderFromEvent(event, '${jsPath}')">▶</span>
                            <span class="file-icon">${icon}</span>
                            <span class="folder-name" onclick="toggleFolder('${jsPath}')">${file.name}</span>
                            <span class="file-actions">
                                <button class="action-btn rename-btn" onclick="renameFile('${jsPath}', '${file.name}')" title="Rename">✏️</button>
                                <button class="action-btn delete-btn" onclick="deleteFile('${jsPath}')" title="Delete">🗑️</button>
                            </span>
                        </div>
                        <ul class="folder-children" data-path="${jsPath}"></ul>
                    </li>
                `;
            } else {
                var className = isEditable ? 'editable' : 'non-editable';
                var onclick = isEditable ? "openFile('" + jsPath + "')" : "event.preventDefault(); showToast('This file type cannot be edited', 'warning');";
                html += `
                    <li class="file" data-path="${jsPath}">
                        <div class="file-item">
                            <span class="file-icon">${icon}</span>
                            <a href="#" class="file-name ${className}" onclick="${onclick}" title="${file.name}">${file.name}</a>
                            ${sizeStr ? '<span class="file-size">' + sizeStr + '</span>' : ''}
                            <span class="file-actions">
                                <button class="action-btn rename-btn" onclick="renameFile('${jsPath}', '${file.name}')" title="Rename">✏️</button>
                                <button class="action-btn delete-btn" onclick="deleteFile('${jsPath}')" title="Delete">🗑️</button>
                            </span>
                        </div>
                    </li>
                `;
            }
        });
        
        html += '</ul>';
        DOM.fileTreeContent.innerHTML = html;
        
        // Store data for reference
        state.fileTreeData = files;
    }

    /**
     * 🔥 Toggle folder expansion
     */
    function toggleFolder(path) {
        console.log('📂 Toggle folder:', path);
        
        // Find the folder LI
        var li = document.querySelector('.folder[data-path="' + path + '"]');
        if (!li) {
            console.warn('Folder not found:', path);
            return;
        }
        
        // Find the children UL
        var children = li.querySelector('ul.folder-children');
        if (!children) {
            console.warn('Children UL not found');
            return;
        }
        
        // Check if already expanded
        var isExpanded = children.classList.contains('expanded');
        
        if (isExpanded) {
            // Collapse
            children.classList.remove('expanded');
            children.style.display = 'none';
            var toggle = li.querySelector('.folder-toggle');
            if (toggle) {
                toggle.textContent = '▶';
                toggle.classList.remove('expanded');
            }
            state.expandedFolders.delete(path);
            console.log('📂 Folder collapsed:', path);
        } else {
            // Expand - load content if empty
            if (children.children.length === 0) {
                // Load children from server
                loadFolderChildren(path, children, li);
            } else {
                // Just show
                children.classList.add('expanded');
                children.style.display = 'block';
                var toggle = li.querySelector('.folder-toggle');
                if (toggle) {
                    toggle.textContent = '▼';
                    toggle.classList.add('expanded');
                }
                state.expandedFolders.add(path);
                console.log('📂 Folder expanded (cached):', path);
            }
        }
    }

    /**
     * 🔥 Load folder children from server
     */
    function loadFolderChildren(path, childrenUl, parentLi) {
        console.log('📂 Loading children for:', path);
        
        // Show loading
        childrenUl.innerHTML = `
            <div class="file-tree-loading">
                <span class="spinner"></span> Loading...
            </div>
        `;
        childrenUl.style.display = 'block';
        
        var normalizedPath = normalizePath(path);
        
        fetch('editor_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=list&dir=' + encodeURIComponent(normalizedPath)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                childrenUl.innerHTML = `
                    <div class="file-tree-empty" style="color: #e74c3c;">
                        ❌ ${data.error}
                    </div>
                `;
                showToast('Error: ' + data.error, 'error');
                return;
            }
            
            if (!data.files || data.files.length === 0) {
                childrenUl.innerHTML = `
                    <div class="file-tree-empty">
                        <span style="font-size: 20px;">📭</span>
                        <div style="color: #555;">Empty folder</div>
                    </div>
                `;
                childrenUl.classList.add('expanded');
                childrenUl.style.display = 'block';
                state.expandedFolders.add(path);
                
                var toggle = parentLi.querySelector('.folder-toggle');
                if (toggle) {
                    toggle.textContent = '▼';
                    toggle.classList.add('expanded');
                }
                return;
            }
            
            // Build child list HTML
            var html = '';
            data.files.forEach(function(file) {
                var icon = file.is_dir ? '📁' : getFileIcon(file.name);
                var jsPath = file.path.replace(/\\/g, '/');
                var sizeStr = formatFileSize(file.size);
                var isEditable = file.editable !== undefined ? file.editable : true;
                
                if (file.is_dir) {
                    html += `
                        <li class="folder" data-path="${jsPath}">
                            <div class="file-item">
                                <span class="folder-toggle" onclick="toggleFolderFromEvent(event, '${jsPath}')">▶</span>
                                <span class="file-icon">${icon}</span>
                                <span class="folder-name" onclick="toggleFolder('${jsPath}')">${file.name}</span>
                                <span class="file-actions">
                                    <button class="action-btn rename-btn" onclick="renameFile('${jsPath}', '${file.name}')" title="Rename">✏️</button>
                                    <button class="action-btn delete-btn" onclick="deleteFile('${jsPath}')" title="Delete">🗑️</button>
                                </span>
                            </div>
                            <ul class="folder-children" data-path="${jsPath}"></ul>
                        </li>
                    `;
                } else {
                    var className = isEditable ? 'editable' : 'non-editable';
                    var onclick = isEditable ? "openFile('" + jsPath + "')" : "event.preventDefault(); showToast('This file type cannot be edited', 'warning');";
                    html += `
                        <li class="file" data-path="${jsPath}">
                            <div class="file-item">
                                <span class="file-icon">${icon}</span>
                                <a href="#" class="file-name ${className}" onclick="${onclick}" title="${file.name}">${file.name}</a>
                                ${sizeStr ? '<span class="file-size">' + sizeStr + '</span>' : ''}
                                <span class="file-actions">
                                    <button class="action-btn rename-btn" onclick="renameFile('${jsPath}', '${file.name}')" title="Rename">✏️</button>
                                    <button class="action-btn delete-btn" onclick="deleteFile('${jsPath}')" title="Delete">🗑️</button>
                                </span>
                            </div>
                        </li>
                    `;
                }
            });
            
            childrenUl.innerHTML = html;
            childrenUl.classList.add('expanded');
            childrenUl.style.display = 'block';
            state.expandedFolders.add(path);
            
            var toggle = parentLi.querySelector('.folder-toggle');
            if (toggle) {
                toggle.textContent = '▼';
                toggle.classList.add('expanded');
            }
            
            console.log('📂 Folder loaded and expanded:', path);
        })
        .catch(function(error) {
            console.error('Error loading folder:', error);
            childrenUl.innerHTML = `
                <div class="file-tree-empty" style="color: #e74c3c;">
                    ❌ Error loading folder
                </div>
            `;
            showToast('Error loading folder contents', 'error');
        });
    }

    /**
     * 🔥 Toggle folder from event (for click on toggle arrow)
     */
    function toggleFolderFromEvent(event, path) {
        event.stopPropagation();
        toggleFolder(path);
    }

    /**
     * 🔥 Expand all folders
     */
    function expandAllFolders() {
        console.log('📂 Expanding all folders...');
        
        var folders = document.querySelectorAll('.folder');
        folders.forEach(function(folder) {
            var path = folder.getAttribute('data-path');
            if (path) {
                var children = folder.querySelector('ul.folder-children');
                if (children) {
                    if (children.children.length === 0) {
                        // Load children
                        loadFolderChildren(path, children, folder);
                    } else {
                        children.classList.add('expanded');
                        children.style.display = 'block';
                        state.expandedFolders.add(path);
                        var toggle = folder.querySelector('.folder-toggle');
                        if (toggle) {
                            toggle.textContent = '▼';
                            toggle.classList.add('expanded');
                        }
                    }
                }
            }
        });
        
        showToast('All folders expanded', 'info');
    }

    /**
     * 🔥 Collapse all folders
     */
    function collapseAllFolders() {
        console.log('📂 Collapsing all folders...');
        
        var childrenLists = document.querySelectorAll('.folder-children');
        childrenLists.forEach(function(list) {
            list.classList.remove('expanded');
            list.style.display = 'none';
        });
        
        var toggles = document.querySelectorAll('.folder-toggle');
        toggles.forEach(function(toggle) {
            toggle.textContent = '▶';
            toggle.classList.remove('expanded');
        });
        
        state.expandedFolders = new Set();
        showToast('All folders collapsed', 'info');
    }

    /**
     * Refresh file tree
     */
    function refreshFileTree() {
        if (state.isSearching) {
            if (state.searchQuery) {
                searchFiles(state.searchQuery);
                return;
            }
            clearSearch();
            return;
        }

        var dir = state.currentDir || '';
        state.isLoading = true;
        
        DOM.fileTreeContent.innerHTML = `
            <div class="file-tree-loading">
                <span class="spinner"></span> Loading files...
            </div>
        `;

        fetch('editor_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=list&dir=' + encodeURIComponent(dir)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            state.isLoading = false;
            
            if (data.error) {
                showToast('Error: ' + data.error, 'error');
                DOM.fileTreeContent.innerHTML = `
                    <div class="file-tree-empty" style="color: #e74c3c;">
                        ❌ ${data.error}
                    </div>
                `;
                return;
            }

            if (data.files) {
                renderFileTree(data.files);
            }
        })
        .catch(function(error) {
            state.isLoading = false;
            console.error('Error refreshing file tree:', error);
            DOM.fileTreeContent.innerHTML = `
                <div class="file-tree-empty" style="color: #e74c3c;">
                    ❌ Error loading files
                </div>
            `;
            showToast('Error refreshing file tree', 'error');
        });
    }

    // ============================================
    // File Management Functions
    // ============================================

    function openFile(filepath) {
        console.log('📂 Opening file:', filepath);
        
        if (!filepath || filepath.trim() === '') {
            showToast('Invalid file path', 'error');
            return;
        }

        var normalizedPath = normalizePath(filepath);

        var existingTab = state.tabs.find(function(t) {
            return normalizePath(t.filepath) === normalizedPath;
        });
        
        if (existingTab) {
            switchToTab(existingTab.id);
            return;
        }

        DOM.statusInfo.textContent = 'Loading...';
        state.isLoading = true;

        fetch('editor_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=open&filepath=' + encodeURIComponent(normalizedPath)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            state.isLoading = false;
            
            if (data.error) {
                showToast('Error: ' + data.error, 'error');
                DOM.statusInfo.textContent = 'Error';
                return;
            }

            if (data.content !== undefined && data.content !== null) {
                addTab(normalizedPath, data.content);
                DOM.statusInfo.textContent = 'Ready - ' + normalizedPath.split('/').pop();
                showToast('File opened: ' + normalizedPath.split('/').pop(), 'success');
            }
        })
        .catch(function(error) {
            state.isLoading = false;
            console.error('Error loading file:', error);
            showToast('Error loading file: ' + error.message, 'error');
            DOM.statusInfo.textContent = 'Error';
        });
    }

    function saveCurrentFile() {
        if (state.activeTabIndex < 0 || state.activeTabIndex >= state.tabs.length) {
            showToast('No file open to save', 'warning');
            return;
        }

        var tab = state.tabs[state.activeTabIndex];
        if (!tab) {
            showToast('Tab not found', 'error');
            return;
        }

        if (!tab.filepath) {
            var filename = prompt('Enter filename:', 'newfile.php');
            if (!filename) return;
            var dir = state.currentDir || '';
            tab.filepath = normalizePath(dir + '/' + filename);
        }

        var content = editor.getValue();
        DOM.statusInfo.textContent = 'Saving...';
        state.isSaving = true;

        var normalizedPath = normalizePath(tab.filepath);

        fetch('editor_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=save&filepath=' + encodeURIComponent(normalizedPath) +
                  '&content=' + encodeURIComponent(content)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            state.isSaving = false;
            
            if (data.error) {
                showToast('Error: ' + data.error, 'error');
                DOM.statusInfo.textContent = 'Error';
                return;
            }

            if (data.success) {
                tab.unsaved = false;
                tab.filepath = normalizedPath;
                markCurrentTabUnsaved(false);
                updateTabName(tab.id, normalizedPath);
                showToast('File saved successfully', 'success');
                DOM.statusInfo.textContent = 'Saved - ' + normalizedPath.split('/').pop();
                refreshFileTree();
            }
        })
        .catch(function(error) {
            state.isSaving = false;
            showToast('Error saving file: ' + error.message, 'error');
            DOM.statusInfo.textContent = 'Error';
        });
    }

    function saveAllFiles() {
        if (state.tabs.length === 0) {
            showToast('No files to save', 'warning');
            return;
        }

        var unsavedTabs = state.tabs.filter(function(t) { return t.unsaved; });
        if (unsavedTabs.length === 0) {
            showToast('All files are already saved', 'info');
            return;
        }

        var saved = 0;
        unsavedTabs.forEach(function(tab) {
            switchToTab(tab.id);
            saveCurrentFile();
            saved++;
        });

        setTimeout(function() {
            if (saved > 0) {
                showToast(saved + ' file(s) saved successfully', 'success');
            }
        }, 500);
    }

    function openNewFile() {
        var filename = prompt('Enter filename:', 'newfile.php');
        if (!filename) return;

        var dir = state.currentDir || '';
        var filepath = normalizePath(dir + '/' + filename);

        fetch('editor_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=create&filepath=' + encodeURIComponent(filepath)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                showToast('Error: ' + data.error, 'error');
                return;
            }

            if (data.success) {
                openFile(filepath);
                refreshFileTree();
                showToast('File created successfully', 'success');
            }
        })
        .catch(function(error) {
            showToast('Error creating file: ' + error.message, 'error');
        });
    }

    function uploadFile(input) {
        var file = input.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function(e) {
            var content = e.target.result;
            var dir = state.currentDir || '';
            var filepath = normalizePath(dir + '/' + file.name);
            
            fetch('editor_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=save&filepath=' + encodeURIComponent(filepath) +
                      '&content=' + encodeURIComponent(content)
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    openFile(filepath);
                    refreshFileTree();
                    showToast('File uploaded successfully', 'success');
                } else {
                    showToast('Error uploading file', 'error');
                }
            })
            .catch(function(error) {
                showToast('Error uploading file: ' + error.message, 'error');
            });
        };
        reader.readAsText(file);
        input.value = '';
    }

    // ============================================
    // Rename & Delete Functions
    // ============================================

    function renameFile(filepath, currentName) {
        var newName = prompt('Enter new name:', currentName);
        if (!newName || newName === currentName) return;

        var normalizedPath = normalizePath(filepath);

        fetch('editor_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=rename&old_path=' + encodeURIComponent(normalizedPath) +
                  '&new_name=' + encodeURIComponent(newName)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                showToast('Error: ' + data.error, 'error');
                return;
            }

            if (data.success) {
                showToast('Renamed successfully', 'success');
                refreshFileTree();
                
                var tab = state.tabs.find(function(t) {
                    return normalizePath(t.filepath) === normalizedPath;
                });
                if (tab) {
                    tab.filepath = data.new_path;
                    tab.filename = newName;
                    renderTabs();
                }
            }
        })
        .catch(function(error) {
            showToast('Error renaming file: ' + error.message, 'error');
        });
    }

    function deleteFile(filepath) {
        if (!confirm('Are you sure you want to delete this item?')) return;

        var normalizedPath = normalizePath(filepath);

        fetch('editor_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete&filepath=' + encodeURIComponent(normalizedPath)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                showToast('Error: ' + data.error, 'error');
                return;
            }

            if (data.success) {
                showToast('Deleted successfully', 'success');
                refreshFileTree();
                
                var tabIndex = state.tabs.findIndex(function(t) {
                    return normalizePath(t.filepath) === normalizedPath;
                });
                if (tabIndex !== -1) {
                    closeTab(state.tabs[tabIndex].id);
                }
            }
        })
        .catch(function(error) {
            showToast('Error deleting file: ' + error.message, 'error');
        });
    }

    // ============================================
    // Search Functions
    // ============================================

    function searchFiles(query) {
        state.searchQuery = query.trim();
        
        if (!state.searchQuery) {
            clearSearch();
            return;
        }

        state.isSearching = true;
        var dir = state.currentDir || '';
        DOM.searchClear.style.display = 'block';

        fetch('editor_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=search&dir=' + encodeURIComponent(dir) +
                  '&query=' + encodeURIComponent(state.searchQuery)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.files) {
                DOM.searchCount.textContent = data.files.length;
                DOM.searchCount.className = 'search-count' + (data.files.length > 0 ? ' has-results' : '');
                renderSearchResults(data.files);
            }
        })
        .catch(function(error) {
            console.error('Search error:', error);
            showToast('Error searching files', 'error');
        });
    }

    function renderSearchResults(files) {
        if (!DOM.fileTreeContent) return;

        if (files.length === 0) {
            DOM.fileTreeContent.innerHTML = `
                <div class="file-tree-empty">
                    <span style="font-size: 32px; display: block; margin-bottom: 10px;">🔍</span>
                    <div style="color: #666; font-weight: 500;">No results found</div>
                    <div style="color: #444; font-size: 11px; margin-top: 4px;">Try a different search term</div>
                </div>
            `;
            return;
        }

        var html = '<ul class="file-tree">';
        var query = state.searchQuery.toLowerCase();
        
        files.forEach(function(file) {
            var icon = file.is_dir ? '📁' : getFileIcon(file.name);
            var isEditable = file.editable !== undefined ? file.editable : true;
            var jsPath = file.path.replace(/\\/g, '/');
            
            var name = file.name;
            var index = name.toLowerCase().indexOf(query);
            if (index !== -1 && !file.is_dir) {
                var highlighted = name.substring(0, index) +
                    '<span style="background: #f39c12; color: #000; padding: 0 2px; border-radius: 2px;">' +
                    name.substring(index, index + query.length) +
                    '</span>' +
                    name.substring(index + query.length);
                name = highlighted;
            }
            
            var sizeStr = formatFileSize(file.size);
            
            if (file.is_dir) {
                html += `
                    <li class="folder" data-path="${jsPath}">
                        <div class="file-item">
                            <span class="file-icon">${icon}</span>
                            <span class="folder-name" onclick="toggleFolder('${jsPath}')">${file.name}</span>
                        </div>
                    </li>
                `;
            } else {
                var className = isEditable ? 'editable' : 'non-editable';
                var onclick = isEditable ? "openFile('" + jsPath + "')" : "event.preventDefault();";
                html += `
                    <li class="file" data-path="${jsPath}">
                        <div class="file-item">
                            <span class="file-icon">${icon}</span>
                            <a href="#" class="file-name ${className}" onclick="${onclick}">${name}</a>
                            ${sizeStr ? '<span class="file-size">' + sizeStr + '</span>' : ''}
                        </div>
                    </li>
                `;
            }
        });
        html += '</ul>';
        DOM.fileTreeContent.innerHTML = html;
    }

    function clearSearch() {
        state.searchQuery = '';
        state.isSearching = false;
        DOM.fileSearch.value = '';
        DOM.searchClear.style.display = 'none';
        DOM.searchCount.textContent = '';
        DOM.searchCount.className = 'search-count';
        refreshFileTree();
    }

    // ============================================
    // Tab Management
    // ============================================

    /**
     * Add a new tab with proper mode detection
     */
    function addTab(filepath, content) {
        var id = state.nextTabId++;
        var filename = filepath.split('/').pop();
        var mode = getModeFromExtension(filepath);

        // 🔥 FIX: Log the detected mode
        console.log('📝 Adding tab:', filename, 'Mode:', mode);

        var tab = {
            id: id,
            filepath: filepath,
            filename: filename,
            content: content || '',
            unsaved: false,
            mode: mode,
            extension: getFileExtension(filename)
        };

        state.tabs.push(tab);
        state.activeTabIndex = state.tabs.length - 1;

        renderTabs();
        
        // 🔥 FIX: Update editor content with proper mode
        setTimeout(function() {
            updateEditorContent(tab);
        }, 100);
        
        updateStatusBar();
    }

    function switchToTab(tabId) {
        var index = state.tabs.findIndex(function(t) { return t.id === tabId; });
        if (index === -1) return;

        if (state.activeTabIndex >= 0 && state.activeTabIndex < state.tabs.length) {
            var currentTab = state.tabs[state.activeTabIndex];
            if (currentTab && editor) {
                currentTab.content = editor.getValue();
            }
        }

        state.activeTabIndex = index;
        var tab = state.tabs[index];

        updateEditorContent(tab);
        renderTabs();

        DOM.statusInfo.textContent = 'Editing - ' + tab.filename;
        DOM.statusMode.textContent = tab.mode || 'Plain Text';

        if (history.pushState) {
            history.pushState(null, null, '?file=' + encodeURIComponent(tab.filepath));
        }
    }

    function closeTab(tabId) {
        var index = state.tabs.findIndex(function(t) { return t.id === tabId; });
        if (index === -1) return;

        var tab = state.tabs[index];

        if (tab.unsaved) {
            if (!confirm('File "' + tab.filename + '" has unsaved changes. Close anyway?')) {
                return;
            }
        }

        state.tabs.splice(index, 1);

        if (state.activeTabIndex === index) {
            if (state.tabs.length === 0) {
                state.activeTabIndex = -1;
                showPlaceholder();
                if (editor) {
                    editor.setValue('');
                    editor.setOption('mode', 'text/plain');
                }
            } else {
                var newIndex = Math.min(index, state.tabs.length - 1);
                state.activeTabIndex = newIndex;
                var newTab = state.tabs[newIndex];
                updateEditorContent(newTab);
            }
        } else if (state.activeTabIndex > index) {
            state.activeTabIndex--;
        }

        renderTabs();
        updateStatusBar();

        if (state.tabs.length === 0) {
            DOM.statusInfo.textContent = 'Ready';
        }
    }

    function closeCurrentTab() {
        if (state.activeTabIndex < 0 || state.activeTabIndex >= state.tabs.length) return;
        var tab = state.tabs[state.activeTabIndex];
        closeTab(tab.id);
    }

    function markCurrentTabUnsaved(unsaved) {
        if (state.activeTabIndex < 0 || state.activeTabIndex >= state.tabs.length) return;
        var tab = state.tabs[state.activeTabIndex];
        tab.unsaved = unsaved;
        renderTabs();
    }

    function updateTabName(tabId, filepath) {
        var tab = state.tabs.find(function(t) { return t.id === tabId; });
        if (!tab) return;
        tab.filename = filepath.split('/').pop();
        tab.filepath = filepath;
        renderTabs();
    }

    /**
     * Update editor content with syntax highlighting
     */
    function updateEditorContent(tab) {
        if (!editor) {
            initEditor();
        }

        if (!editor) {
            console.error('Editor not initialized');
            return;
        }

        DOM.editorPlaceholder.style.display = 'none';
        DOM.codeEditor.style.display = 'none';

        var cmWrapper = document.querySelector('.CodeMirror');
        if (cmWrapper) {
            cmWrapper.style.display = 'block';
        }

        try {
            var content = tab.content || '';
            if (typeof content !== 'string') {
                content = String(content);
            }
            
            // 🔥 FIX: Set content
            editor.setValue(content);
            
            // 🔥 FIX: Set proper mode for syntax highlighting
            var mode = tab.mode || 'text/plain';
            editor.setOption('mode', mode);
            
            // 🔥 FIX: Set theme for better highlighting
            editor.setOption('theme', 'default');
            
            // 🔥 FIX: Enable line numbers
            editor.setOption('lineNumbers', true);
            
            // 🔥 FIX: Enable bracket matching
            editor.setOption('matchBrackets', true);
            
            // 🔥 FIX: Enable active line highlighting
            editor.setOption('styleActiveLine', true);
            
            // Update status bar
            DOM.statusMode.textContent = tab.mode || 'Plain Text';

            // Refresh editor
            setTimeout(function() {
                editor.refresh();
                editor.focus();
                DOM.codeEditor.style.display = 'none';
            }, 200);
            
            updateStatusBar();
            
            console.log('📝 Editor updated with mode:', mode);
            console.log('📝 Content length:', content.length);
            
        } catch (e) {
            console.error('Error updating editor content:', e);
            showToast('Error displaying file content', 'error');
        }
    }

    function showPlaceholder() {
        DOM.editorPlaceholder.style.display = 'flex';
        DOM.codeEditor.style.display = 'none';
        
        var cmWrapper = document.querySelector('.CodeMirror');
        if (cmWrapper) {
            cmWrapper.style.display = 'none';
        }
    }

    function renderTabs() {
        if (!DOM.tabsContainer) return;

        if (state.tabs.length === 0) {
            DOM.tabsContainer.innerHTML = '';
            showPlaceholder();
            DOM.statusInfo.textContent = 'Ready';
            return;
        }

        var html = '';
        state.tabs.forEach(function(tab) {
            var isActive = state.activeTabIndex >= 0 &&
                           state.tabs[state.activeTabIndex] &&
                           state.tabs[state.activeTabIndex].id === tab.id;
            
            var icon = getFileIcon(tab.filename);
            var unsavedMark = tab.unsaved ? '<span class="tab-unsaved">●</span>' : '';
            
            html += `
                <div class="tab-item ${isActive ? 'active' : ''}" 
                     onclick="switchToTab(${tab.id})"
                     title="${tab.filepath}">
                    <span class="tab-icon">${icon}</span>
                    <span class="tab-name">${tab.filename}</span>
                    ${unsavedMark}
                    <span class="tab-close" onclick="event.stopPropagation(); closeTab(${tab.id})">×</span>
                </div>
            `;
        });

        DOM.tabsContainer.innerHTML = html;

        var activeTab = DOM.tabsContainer.querySelector('.tab-item.active');
        if (activeTab) {
            activeTab.scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    }

    // ============================================
    // Keyboard Shortcuts
    // ============================================

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'S' || e.key === 's')) {
            e.preventDefault();
            saveAllFiles();
        }
        
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            if (DOM.fileSearch) {
                DOM.fileSearch.focus();
                DOM.fileSearch.select();
            }
        }
        
        if (e.key === 'Escape') {
            if (DOM.fileSearch && document.activeElement === DOM.fileSearch) {
                clearSearch();
                DOM.fileSearch.blur();
            }
        }
    });

    // ============================================
    // Expose Functions Globally
    // ============================================

    window.openFile = openFile;
    window.switchToTab = switchToTab;
    window.closeTab = closeTab;
    window.saveCurrentFile = saveCurrentFile;
    window.saveAllFiles = saveAllFiles;
    window.openNewFile = openNewFile;
    window.uploadFile = uploadFile;
    window.refreshFileTree = refreshFileTree;
    window.toggleFolder = toggleFolder;
    window.toggleFolderFromEvent = toggleFolderFromEvent;
    window.showToast = showToast;
    window.renameFile = renameFile;
    window.deleteFile = deleteFile;
    window.searchFiles = searchFiles;
    window.clearSearch = clearSearch;
    window.expandAllFolders = expandAllFolders;
    window.collapseAllFolders = collapseAllFolders;
    window.loadFolderChildren = loadFolderChildren;

    // ============================================
    // Initialize
    // ============================================

    function initialize() {
        // Get current directory from PHP
        if (typeof window.currentDir !== 'undefined') {
            state.currentDir = window.currentDir;
        } else if (DOM.currentDir) {
            var dirText = DOM.currentDir.textContent.replace('📂', '').trim();
            state.currentDir = dirText.replace(/\\/g, '/');
        }

        console.log('⏳ Initializing editor...');
        console.log('📂 Current directory:', state.currentDir);

        // Initialize CodeMirror
        initEditor();
        showPlaceholder();

        // Render file tree from PHP data
        if (typeof window.rootFiles !== 'undefined' && window.rootFiles.length > 0) {
            renderFileTree(window.rootFiles);
        } else {
            // Refresh from server
            setTimeout(function() {
                refreshFileTree();
            }, 300);
        }

        // Check for file in URL
        var urlParams = new URLSearchParams(window.location.search);
        var fileParam = urlParams.get('file');
        if (fileParam) {
            setTimeout(function() {
                openFile(fileParam);
            }, 600);
        }

        console.log('✅ Web Shell Pro Editor loaded successfully');
        console.log('💡 Keyboard shortcuts:');
        console.log('  • Ctrl+S - Save');
        console.log('  • Ctrl+W - Close tab');
        console.log('  • Ctrl+N - New file');
        console.log('  • Ctrl+F - Search');
        console.log('  • Esc - Clear search');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

})();