/**
 * terminal.js - Web Shell Pro Terminal Scripts (English Version)
 * Version: 2.0.0
 * 
 * Features:
 * - Command history management (↑/↓)
 * - Quick command execution
 * - Copy output on click
 * - Auto-focus
 * - Prevent form resubmission
 * - Tab auto-completion
 */

(function() {
    'use strict';

    // ============================================
    // DOM Elements
    // ============================================

    const commandInput = document.getElementById('commandInput');
    const output = document.getElementById('output');
    const commandForm = document.getElementById('commandForm');
    const executeBtn = document.getElementById('executeBtn');

    // ============================================
    // Command History
    // ============================================

    let history = [];
    let historyIndex = -1;

    // Add previous commands to history (from PHP)
    if (typeof window.initialHistory !== 'undefined' && Array.isArray(window.initialHistory)) {
        history = window.initialHistory;
        historyIndex = history.length;
    }

    // ============================================
    // Utility Functions
    // ============================================

    /**
     * Scroll to bottom of output
     */
    function scrollToBottom() {
        if (output) {
            output.scrollTop = output.scrollHeight;
        }
    }

    /**
     * Focus on command input
     */
    function focusInput() {
        if (commandInput) {
            commandInput.focus();
        }
    }

    /**
     * Add command to history
     * @param {string} cmd - Command to add
     */
    function addToHistory(cmd) {
        if (cmd && cmd.trim()) {
            const trimmedCmd = cmd.trim();
            if (history.length === 0 || history[history.length - 1] !== trimmedCmd) {
                history.push(trimmedCmd);
                if (history.length > 100) {
                    history.shift();
                }
            }
            historyIndex = history.length;
        }
    }

    /**
     * Execute command (for quick buttons)
     * @param {string} cmd - Command to execute
     */
    window.runCommand = function(cmd) {
        if (commandInput && commandForm) {
            commandInput.value = cmd;
            addToHistory(cmd);
            commandForm.submit();
        }
    };

    // ============================================
    // Keyboard Events
    // ============================================

    if (commandInput) {
        commandInput.addEventListener('keydown', function(e) {
            // Up Arrow - Previous command
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (historyIndex > 0) {
                    historyIndex--;
                    this.value = history[historyIndex];
                }
                setTimeout(function() {
                    this.setSelectionRange(this.value.length, this.value.length);
                }.bind(this), 0);
            }
            // Down Arrow - Next command
            else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (historyIndex < history.length - 1) {
                    historyIndex++;
                    this.value = history[historyIndex];
                } else {
                    historyIndex = history.length;
                    this.value = '';
                }
            }
            // Ctrl+Enter for quick execution
            else if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                if (commandForm) {
                    const cmd = this.value.trim();
                    if (cmd) {
                        addToHistory(cmd);
                    }
                    commandForm.submit();
                }
            }
            // Esc to clear input
            else if (e.key === 'Escape') {
                this.value = '';
                historyIndex = history.length;
                e.preventDefault();
            }
            // Tab for auto-completion
            else if (e.key === 'Tab') {
                e.preventDefault();
                const cmd = this.value.trim();
                if (cmd) {
                    // Common commands list for auto-completion
                    const commonCommands = [
                        'ls', 'ls -la', 'pwd', 'cd ..', 'cd /',
                        'clear', 'help', 'check_functions', 'phpinfo', 'php_version',
                        'whoami', 'hostname', 'date', 'time', 'ps', 'ifconfig',
                        'mkdir', 'rmdir', 'cp', 'mv', 'cat', 'grep', 'find'
                    ];
                    const matched = commonCommands.filter(function(c) {
                        return c.startsWith(cmd);
                    });
                    if (matched.length === 1) {
                        this.value = matched[0];
                        setTimeout(function() {
                            this.setSelectionRange(this.value.length, this.value.length);
                        }.bind(this), 0);
                    }
                }
            }
        });

        // ============================================
        // Form Submit Event
        // ============================================

        if (commandForm) {
            commandForm.addEventListener('submit', function() {
                const cmd = commandInput.value.trim();
                if (cmd) {
                    addToHistory(cmd);
                }
            });
        }

        // ============================================
        // Click on output to refocus
        // ============================================

        if (output) {
            output.addEventListener('click', focusInput);
        }

        // ============================================
        // Copy output on click
        // ============================================

        document.addEventListener('click', function(e) {
            const target = e.target.closest('.command-output');
            if (target) {
                const text = target.textContent;
                if (text && text.trim()) {
                    copyToClipboard(text.trim(), target);
                }
            }
        });

        /**
         * Copy text to clipboard
         * @param {string} text - Text to copy
         * @param {HTMLElement} element - Element for feedback
         */
        function copyToClipboard(text, element) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text)
                    .then(function() {
                        showCopyFeedback(element, true);
                    })
                    .catch(function() {
                        fallbackCopy(text, element);
                    });
            } else {
                fallbackCopy(text, element);
            }
        }

        /**
         * Fallback copy method (for older browsers)
         * @param {string} text - Text to copy
         * @param {HTMLElement} element - Element for feedback
         */
        function fallbackCopy(text, element) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                const success = document.execCommand('copy');
                showCopyFeedback(element, success);
            } catch (err) {
                showCopyFeedback(element, false);
            }
            document.body.removeChild(textarea);
        }

        /**
         * Show copy feedback
         * @param {HTMLElement} element - Target element
         * @param {boolean} success - Copy status
         */
        function showCopyFeedback(element, success) {
            const originalBg = element.style.background;
            if (success) {
                element.style.background = '#1a3a1a';
                element.style.borderLeftColor = '#4CAF50';
            } else {
                element.style.background = '#3a1a1a';
                element.style.borderLeftColor = '#e74c3c';
            }
            setTimeout(function() {
                element.style.background = originalBg || '';
                element.style.borderLeftColor = '';
            }, 500);
        }
    }

    // ============================================
    // Prevent form resubmission with F5
    // ============================================

    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // ============================================
    // Execute on load
    // ============================================

    // Scroll to bottom
    scrollToBottom();

    // Auto-focus
    setTimeout(focusInput, 100);

    // ============================================
    // Console Information
    // ============================================

    console.log('✅ Web Shell Pro loaded successfully');
    console.log('💡 Type "help" for available commands');
    console.log('📋 Version: 2.0.0');

    // ============================================
    // Public API
    // ============================================

    window.Terminal = {
        focus: focusInput,
        scrollToBottom: scrollToBottom,
        addToHistory: addToHistory,
        getHistory: function() { return history.slice(); },
        clearHistory: function() {
            history = [];
            historyIndex = -1;
        },
        runCommand: window.runCommand
    };

})();