{{-- Share File/Folder Form --}}
<form action="{{ route('share.item') }}" method="POST">
    @csrf
    <label for="item_type">Select Type:</label>
    <select name="item_type" id="item_type">
        <option value="file">File</option>
        <option value="folder">Folder</option>
    </select>

    <label for="item_id">Item ID:</label>
    <input type="number" name="item_id" required>

    <label for="shared_with_id">Share With User ID:</label>
    <input type="number" name="shared_with_id" required>

    <label for="permission">Permission:</label>
    <select name="permission" id="permission">
        <option value="read">Read</option>
        <option value="write">Write</option>
    </select>

    <button type="submit">Share</button>
</form>
