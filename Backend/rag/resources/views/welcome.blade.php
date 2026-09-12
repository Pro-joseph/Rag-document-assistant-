<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'RAG Document Assistant') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
            </style>
        @endif
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex items-center justify-center min-h-screen">
        <div class="w-full max-w-lg mx-auto p-8">
            <div class="bg-white dark:bg-[#161615] rounded-lg shadow-lg p-8">
                <h1 class="text-2xl font-semibold mb-6 text-center">Upload Document</h1>

                <form id="uploadForm" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-6">
                        <label for="fileInput" class="block text-sm font-medium mb-2 text-[#1b1b18] dark:text-[#EDEDEC]">
                            Select a document
                        </label>
                        <input
                            type="file"
                            id="fileInput"
                            name="file"
                            class="block w-full text-sm text-[#706f6c] dark:text-[#A1A09A] file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-[#f53003] file:text-white hover:file:bg-[#d92902] file:cursor-pointer"
                            accept=".pdf,.docx,.txt,.csv"
                        >
                    </div>

                    <div id="selectedFile" class="mb-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        No file selected
                    </div>

                    <div id="clientError" class="mb-4 text-sm text-red-600 hidden"></div>

                    <div id="serverMessage" class="mb-4 text-sm hidden"></div>

                    <button
                        type="submit"
                        id="uploadButton"
                        disabled
                        class="w-full bg-[#f53003] hover:bg-[#d92902] disabled:bg-[#ccc] disabled:cursor-not-allowed text-white font-semibold py-2 px-4 rounded-md transition-colors"
                    >
                        Upload
                    </button>
                </form>
            </div>
        </div>

        <script>
            const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'txt', 'csv'];
            const MAX_SIZE_BYTES = 10 * 1024 * 1024;

            const fileInput = document.getElementById('fileInput');
            const selectedFile = document.getElementById('selectedFile');
            const uploadButton = document.getElementById('uploadButton');
            const clientError = document.getElementById('clientError');
            const serverMessage = document.getElementById('serverMessage');
            const uploadForm = document.getElementById('uploadForm');

            function validateFile(file) {
                if (!file) {
                    return { valid: false, message: 'Please select a file.' };
                }

                const ext = file.name.split('.').pop().toLowerCase();
                if (!ALLOWED_EXTENSIONS.includes(ext)) {
                    return { valid: false, message: 'Unsupported file type. Allowed: PDF, DOCX, TXT, CSV.' };
                }

                if (file.size > MAX_SIZE_BYTES) {
                    return { valid: false, message: 'File is too large. Maximum size is 10 MB.' };
                }

                return { valid: true, message: '' };
            }

            function formatFileSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            }

            function showMessage(element, text, type) {
                element.textContent = text;
                element.className = 'mb-4 text-sm text-' + type + '-600';
            }

            function hideMessages() {
                clientError.classList.add('hidden');
                serverMessage.classList.add('hidden');
            }

            fileInput.addEventListener('change', function () {
                hideMessages();

                const file = this.files[0];
                if (!file) {
                    selectedFile.textContent = 'No file selected';
                    uploadButton.disabled = true;
                    return;
                }

                const validation = validateFile(file);
                selectedFile.textContent = 'Selected: ' + file.name + ' (' + formatFileSize(file.size) + ')';

                if (!validation.valid) {
                    showMessage(clientError, validation.message, 'red');
                    uploadButton.disabled = true;
                } else {
                    uploadButton.disabled = false;
                }
            });

            uploadForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                const file = fileInput.files[0];
                if (!file) {
                    showMessage(clientError, 'Please select a file.', 'red');
                    return;
                }

                hideMessages();
                uploadButton.disabled = true;
                uploadButton.textContent = 'Uploading...';

                const formData = new FormData();
                formData.append('file', file);
                formData.append('_token', document.querySelector('input[name="_token"]').value);

                try {
                    const response = await fetch('/upload', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    const data = await response.json();

                    if (response.ok && data.status === 'success') {
                        showMessage(serverMessage, 'File uploaded successfully!', 'green');
                        fileInput.value = '';
                        selectedFile.textContent = 'No file selected';
                    } else {
                        let errorMessage = 'Upload failed. Please try again.';
                        if (data.errors && data.errors.file) {
                            errorMessage = data.errors.file[0];
                        } else if (data.message) {
                            errorMessage = data.message;
                        }
                        showMessage(serverMessage, errorMessage, 'red');
                    }
                } catch (error) {
                    showMessage(serverMessage, 'Upload failed. Please try again.', 'red');
                } finally {
                    uploadButton.disabled = false;
                    uploadButton.textContent = 'Upload';
                }
            });
        </script>
    </body>
</html>
