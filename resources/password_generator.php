<!-- Modal generatora hasla (wspolny dla add.php i edit.php) -->
<div class="modal fade" id="genPasswordModal" tabindex="-1" role="dialog" aria-labelledby="genPasswordModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="genPasswordModalLabel"><?php echo _("Generate password"); ?></h4>
      </div>
      <div class="modal-body">
        <div id="genPasswordOptions">
        <div class="form-group">
          <label><?php echo _("Generation method"); ?></label>
          <div>
            <label class="radio-inline"><input type="radio" name="genMode" value="random" checked> <?php echo _("Random"); ?></label>
            <label class="radio-inline"><input type="radio" name="genMode" value="passphrase"> <?php echo _("Passphrase"); ?></label>
            <label class="radio-inline"><input type="radio" name="genMode" value="syllables"> <?php echo _("Melodic"); ?></label>
          </div>
        </div>
        <div id="genRandomOptions">
        <div class="form-group">
          <label for="genLength"><?php echo _("Password length"); ?></label>
          <input type="number" class="form-control" id="genLength" min="4" max="256" value="16">
        </div>
        <div class="form-group">
          <label for="genDigits"><?php echo _("Number of digits"); ?></label>
          <input type="number" class="form-control" id="genDigits" min="0" max="256" value="2">
        </div>
        <div class="form-group">
          <label for="genSpecial"><?php echo _("Number of special characters"); ?></label>
          <input type="number" class="form-control" id="genSpecial" min="0" max="256" value="2">
        </div>
        </div>
        <div id="genPassphraseOptions" style="display:none;">
        <div class="form-group">
          <label for="genWords"><?php echo _("Number of words"); ?></label>
          <input type="number" class="form-control" id="genWords" min="3" max="12" value="5">
        </div>
        <div class="form-group">
          <label for="genSeparator"><?php echo _("Word separator"); ?></label>
          <select class="form-control" id="genSeparator">
            <option value="-"><?php echo _("Hyphen"); ?> ( - )</option>
            <option value="."><?php echo _("Dot"); ?> ( . )</option>
            <option value="_"><?php echo _("Underscore"); ?> ( _ )</option>
            <option value=" "><?php echo _("Space"); ?></option>
            <option value=""><?php echo _("None"); ?></option>
          </select>
        </div>
        <div class="checkbox"><label><input type="checkbox" id="genCapitalize" checked> <?php echo _("Capitalize words"); ?></label></div>
        <div class="checkbox"><label><input type="checkbox" id="genAddNumber" checked> <?php echo _("Add a number"); ?></label></div>
        </div>
        <div id="genSyllablesOptions" style="display:none;">
        <p class="help-block"><?php echo _("An easy-to-say password built from syllables."); ?></p>
        <div class="form-group">
          <label for="genSylLength"><?php echo _("Password length"); ?></label>
          <input type="number" class="form-control" id="genSylLength" min="4" max="256" value="12">
        </div>
        <div class="form-group">
          <label for="genSylDigits"><?php echo _("Number of digits"); ?></label>
          <input type="number" class="form-control" id="genSylDigits" min="0" max="256" value="1">
        </div>
        <div class="form-group">
          <label for="genSylSpecial"><?php echo _("Number of special characters"); ?></label>
          <input type="number" class="form-control" id="genSylSpecial" min="0" max="256" value="1">
        </div>
        </div>
        </div>
        <div id="genPasswordResult" style="display:none;">
        <div class="form-group">
          <label for="genResultValue"><?php echo _("Generated password"); ?></label>
          <input type="text" class="form-control" id="genResultValue" readonly>
        </div>
        </div>
      </div>
      <div class="modal-footer">
        <div id="genPasswordFooterOptions">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _("Cancel"); ?></button>
        <button type="button" class="btn btn-primary" id="genPasswordSubmit"><?php echo _("Generate"); ?></button>
        </div>
        <div id="genPasswordFooterResult" style="display:none;">
        <button type="button" class="btn btn-default" id="genPasswordCancelResult"><?php echo _("Cancel"); ?></button>
        <button type="button" class="btn btn-default" id="genPasswordBack"><?php echo _("Options"); ?></button>
        <button type="button" class="btn btn-default" id="genPasswordRegenerate"><?php echo _("Generate again"); ?></button>
        <button type="button" class="btn btn-primary" id="genPasswordAccept"><?php echo _("Accept"); ?></button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var GEN_MODE_KEY = 'phpMyPassword.generatorMode';

  function el(id) { return document.getElementById(id); }

  function wybranyTryb() {
    var zaznaczony = document.querySelector('input[name="genMode"]:checked');
    return zaznaczony ? zaznaczony.value : 'random';
  }

  // Pokazuje opcje wlasciwe dla wybranego sposobu generowania
  function pokazOpcjeTrybu() {
    var tryb = wybranyTryb();
    el('genRandomOptions').style.display = (tryb === 'random') ? '' : 'none';
    el('genPassphraseOptions').style.display = (tryb === 'passphrase') ? '' : 'none';
    el('genSyllablesOptions').style.display = (tryb === 'syllables') ? '' : 'none';
  }

  function liczba(id, domyslna) {
    var n = parseInt(el(id).value, 10);
    return isNaN(n) ? domyslna : n;
  }

  function parametry() {
    var p = new URLSearchParams();
    p.set('mode', wybranyTryb());
    if (wybranyTryb() === 'passphrase') {
      p.set('words', liczba('genWords', 5));
      p.set('separator', el('genSeparator').value);
      p.set('capitalize', el('genCapitalize').checked ? 1 : 0);
      p.set('number', el('genAddNumber').checked ? 1 : 0);
    }
    else if (wybranyTryb() === 'syllables') {
      p.set('length', liczba('genSylLength', 12));
      p.set('digits', liczba('genSylDigits', 1));
      p.set('special', liczba('genSylSpecial', 1));
    }
    else {
      p.set('length', liczba('genLength', 16));
      p.set('digits', liczba('genDigits', 0));
      p.set('special', liczba('genSpecial', 0));
    }
    return p;
  }

  function pokazOpcje() {
    el('genPasswordOptions').style.display = '';
    el('genPasswordResult').style.display = 'none';
    el('genPasswordFooterOptions').style.display = '';
    el('genPasswordFooterResult').style.display = 'none';
  }

  function generujIPokaz() {
    fetch('genpassword.php?' + parametry().toString(), { credentials: 'same-origin', cache: 'no-store' })
      .then(function(response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
      })
      .then(function(haslo) {
        el('genResultValue').value = haslo;
        el('genPasswordOptions').style.display = 'none';
        el('genPasswordResult').style.display = '';
        el('genPasswordFooterOptions').style.display = 'none';
        el('genPasswordFooterResult').style.display = '';
      })
      .catch(function(error) {
        console.error('Blad generowania hasla:', error);
      });
  }

  // Zapamietany sposob generowania (tylko wygoda - brak dostepu do localStorage niczego nie psuje)
  try {
    var zapamietany = window.localStorage.getItem(GEN_MODE_KEY);
    var radio = zapamietany && document.querySelector('input[name="genMode"][value="' + zapamietany + '"]');
    if (radio) radio.checked = true;
  } catch (e) {}
  pokazOpcjeTrybu();

  Array.prototype.forEach.call(document.querySelectorAll('input[name="genMode"]'), function(radio) {
    radio.addEventListener('change', function() {
      pokazOpcjeTrybu();
      try { window.localStorage.setItem(GEN_MODE_KEY, wybranyTryb()); } catch (e) {}
    });
  });

  el('genpassword').addEventListener('click', function(e) {
    e.preventDefault();
    pokazOpcje();
    $('#genPasswordModal').modal('show');
  });

  el('genPasswordSubmit').addEventListener('click', generujIPokaz);
  el('genPasswordRegenerate').addEventListener('click', generujIPokaz);
  el('genPasswordBack').addEventListener('click', pokazOpcje);

  el('genPasswordAccept').addEventListener('click', function() {
    var pole = el('password');
    pole.value = el('genResultValue').value;
    pole.classList.remove('pole-hasla-ukryte');
    pole.dispatchEvent(new Event('input'));
    $('#genPasswordModal').modal('hide');
  });

  el('genPasswordCancelResult').addEventListener('click', function() {
    $('#genPasswordModal').modal('hide');
  });
})();
</script>
