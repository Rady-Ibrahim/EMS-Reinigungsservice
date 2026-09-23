<div class="form-group field-error" style="background:#fffbeb;border:1px solid #fde68a;padding:.6rem .9rem;border-radius:8px;">
    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;color:#92400e;">
        <input type="checkbox" name="force" value="1" {{ old('force') ? 'checked' : '' }}>
        Konflikt trotzdem erzwingen (Überschreibt Doppelbuchung)
    </label>
    <small>Nur für Administratoren verfügbar — bei Konflikten wird eine Benachrichtigung protokolliert.</small>
</div>