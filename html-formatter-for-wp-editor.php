<?php
/**
 * Plugin Name: HTML Formatter for WP Editor
 * Description: 投稿・固定ページの編集画面にHTMLを整形するボタンを追加します。ワンクリックでテキストエディタのHTMLを見やすく整形できます。
 * Version: 1.6.0
 * Author: てらさきみくる
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: html-formatter-for-wp
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class HTML_Formatter_For_WP_Editor {
    
    public function __construct() {
        // エディター画面にボタンとスクリプトを追加
        add_action('admin_footer-post.php', array($this, 'add_editor_button'));
        add_action('admin_footer-post-new.php', array($this, 'add_editor_button'));
        
        // AJAXハンドラーを登録
        add_action('wp_ajax_format_html_editor', array($this, 'ajax_format_html'));
        
        // 管理画面にスタンドアロンのページも追加
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * エディター画面にボタンとスクリプトを追加
     */
    public function add_editor_button() {
        ?>
        <style>
            #html-formatter-btn {
                margin: 10px 0;
                padding: 8px 15px;
                background: #2271b1;
                color: white;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 600;
            }
            #html-formatter-btn:hover {
                background: #135e96;
            }
            #html-formatter-btn:disabled {
                background: #ccc;
                cursor: not-allowed;
            }
            .html-formatter-message {
                margin: 10px 0;
                padding: 8px 12px;
                border-radius: 3px;
                display: none;
            }
            .html-formatter-message.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .html-formatter-message.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            #html-formatter-container {
                margin: 10px 0;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // テキストエディタのタブが存在するか確認
            var textEditorTab = $('#content-html');
            
            if (textEditorTab.length) {
                // ボタンとメッセージエリアを追加
                var container = $('<div id="html-formatter-container"></div>');
                var button = $('<button type="button" id="html-formatter-btn">📝 HTMLを整形する</button>');
                var message = $('<div class="html-formatter-message"></div>');
                
                container.append(button).append(message);
                
                // エディターツールバーの下に挿入
                $('#wp-content-editor-tools').after(container);
                
                // ボタンクリックイベント
                button.on('click', function() {
                    var btn = $(this);
                    var msg = $('.html-formatter-message');
                    
                    // エディターの種類を判定
                    var isVisualMode = $('#content-tmce').hasClass('active');
                    
                    if (isVisualMode) {
                        // ビジュアルエディタモードの場合、テキストモードに切り替えを促す
                        msg.removeClass('success').addClass('error')
                           .text('テキストエディタモードに切り替えてから実行してください')
                           .fadeIn().delay(3000).fadeOut();
                        return;
                    }
                    
                    // テキストエディタのコンテンツを取得
                    var content = '';
                    
                    // WordPress 5.0以降のブロックエディタ対応
                    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                        // ブロックエディタの場合
                        msg.removeClass('success').addClass('error')
                           .text('このプラグインは旧エディタ（クラシックエディタ）専用です')
                           .fadeIn().delay(3000).fadeOut();
                        return;
                    } else {
                        // クラシックエディタの場合
                        content = $('#content').val();
                    }
                    
                    if (!content || !content.trim()) {
                        msg.removeClass('success').addClass('error')
                           .text('コンテンツが空です')
                           .fadeIn().delay(3000).fadeOut();
                        return;
                    }
                    
                    // ボタンを無効化
                    btn.prop('disabled', true).text('整形中...');
                    msg.hide();
                    
                    // AJAX送信
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'format_html_editor',
                            html: content,
                            nonce: '<?php echo wp_create_nonce('format_html_editor_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // エディタに整形済みHTMLをセット
                                $('#content').val(response.data);
                                
                                msg.removeClass('error').addClass('success')
                                   .text('✓ HTMLの整形が完了しました！')
                                   .fadeIn().delay(2000).fadeOut();
                            } else {
                                msg.removeClass('success').addClass('error')
                                   .text('エラー: ' + response.data)
                                   .fadeIn().delay(3000).fadeOut();
                            }
                        },
                        error: function() {
                            msg.removeClass('success').addClass('error')
                               .text('通信エラーが発生しました')
                               .fadeIn().delay(3000).fadeOut();
                        },
                        complete: function() {
                            // ボタンを再度有効化
                            btn.prop('disabled', false).text('📝 HTMLを整形する');
                        }
                    });
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: HTMLを整形
     */
    public function ajax_format_html() {
        // nonceチェック
        check_ajax_referer('format_html_editor_nonce', 'nonce');
        
        // 権限チェック
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
            return;
        }
        
        $html = isset($_POST['html']) ? stripslashes($_POST['html']) : '';
        
        if (empty($html)) {
            wp_send_json_error('HTMLコードが空です');
            return;
        }
        
        // HTMLを整形
        $formatted = $this->format_html($html);
        
        wp_send_json_success($formatted);
    }
    
    /**
     * HTMLコードを整形
     */
    private function format_html($html) {
        // 既存の改行とタブを削除（整形のため）
        $html = preg_replace('/\s*\n\s*/', '', $html);
        $html = preg_replace('/\s{2,}/', ' ', $html);
        
        // </p> の後に改行2つ（空行1つ）
        $html = preg_replace('/<\/p>/', '</p>' . "\n\n", $html);
        
        // 見出しタグの後に改行2つ
        $html = preg_replace('/<\/(h[2-6])>/', '</$1>' . "\n\n", $html);
        
        // </blockquote> と </figure> の後に改行2つ
        $html = preg_replace('/<\/(blockquote|figure)>/', '</$1>' . "\n\n", $html);
        
        // <figure> の後に改行1つ
        $html = preg_replace('/<figure([^>]*)>/', '<figure$1>' . "\n", $html);
        
        // テーブル関連タグの整形
        $html = preg_replace('/<table([^>]*)>/', '<table$1>' . "\n", $html);
        $html = preg_replace('/<\/table>/', '</table>' . "\n", $html);
        $html = preg_replace('/<(thead|tbody|tfoot)([^>]*)>/', '<$1$2>' . "\n", $html);
        $html = preg_replace('/<\/(thead|tbody|tfoot)>/', '</$1>' . "\n", $html);
        $html = preg_replace('/<tr([^>]*)>/', '<tr$1>' . "\n", $html);
        $html = preg_replace('/<\/tr>/', '</tr>' . "\n", $html);
        $html = preg_replace('/<(th|td)([^>]*)>/', '<$1$2>', $html);
        $html = preg_replace('/<\/(th|td)>/', '</$1>' . "\n", $html);
        
        // リスト関連タグの整形
        $html = preg_replace('/<(ul|ol)([^>]*)>/', '<$1$2>' . "\n", $html);
        $html = preg_replace('/<\/(ul|ol)>/', '</$1>' . "\n\n", $html);
        $html = preg_replace('/<li([^>]*)>/', '<li$1>', $html);
        $html = preg_replace('/<\/li>/', '</li>' . "\n", $html);
        
        // <div> の後に改行1つ
        $html = preg_replace('/<div([^>]*)>/', '<div$1>' . "\n", $html);
        $html = preg_replace('/<\/div>/', '</div>' . "\n", $html);
        
        // 先頭と末尾の余分な改行を削除
        $html = trim($html);
        
        // 連続する改行を最大2つまでに制限
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        
        return $html;
    }
    
    /**
     * 管理画面にメニューを追加（スタンドアロン版）
     */
    public function add_admin_menu() {
        add_management_page(
            'HTML Formatter',
            'HTML Formatter',
            'manage_options',
            'html-formatter',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 管理画面のページを表示（スタンドアロン版）
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>HTML Formatter - HTMLコード整形ツール</h1>
            <p>ビジュアルエディターで崩れたHTMLコードを整形します。</p>
            <p><strong>💡 ヒント:</strong> 投稿・固定ページの編集画面でも「📝 HTMLを整形する」ボタンが使えます！</p>
            
            <div style="max-width: 1200px;">
                <h2>整形前のHTML</h2>
                <textarea id="html-input" style="width: 100%; height: 300px; font-family: monospace; font-size: 13px;"></textarea>
                
                <p>
                    <button type="button" id="format-btn" class="button button-primary button-large">
                        整形する
                    </button>
                    <button type="button" id="clear-btn" class="button button-secondary">
                        クリア
                    </button>
                </p>
                
                <h2>整形後のHTML</h2>
                <textarea id="html-output" style="width: 100%; height: 300px; font-family: monospace; font-size: 13px;" readonly></textarea>
                
                <p>
                    <button type="button" id="copy-btn" class="button button-secondary">
                        クリップボードにコピー
                    </button>
                </p>
                
                <div id="message" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <style>
            #message.success {
                color: #46b450;
                font-weight: bold;
            }
            #message.error {
                color: #dc3232;
                font-weight: bold;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#format-btn').on('click', function() {
                var input = $('#html-input').val();
                
                if (!input.trim()) {
                    showMessage('HTMLコードを入力してください', 'error');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'format_html_editor',
                        html: input,
                        nonce: '<?php echo wp_create_nonce('format_html_editor_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#html-output').val(response.data);
                            showMessage('整形が完了しました！', 'success');
                        } else {
                            showMessage('エラー: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showMessage('通信エラーが発生しました', 'error');
                    }
                });
            });
            
            $('#clear-btn').on('click', function() {
                $('#html-input').val('');
                $('#html-output').val('');
                $('#message').html('').removeClass('success error');
            });
            
            $('#copy-btn').on('click', function() {
                var output = $('#html-output').val();
                
                if (!output) {
                    showMessage('コピーする内容がありません', 'error');
                    return;
                }
                
                $('#html-output').select();
                document.execCommand('copy');
                showMessage('クリップボードにコピーしました！', 'success');
            });
            
            function showMessage(message, type) {
                $('#message')
                    .html(message)
                    .removeClass('success error')
                    .addClass(type);
            }
        });
        </script>
        <?php
    }
}

// プラグインを初期化
new HTML_Formatter_For_WP_Editor();
