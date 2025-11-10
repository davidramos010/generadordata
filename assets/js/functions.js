// Función para generar un NIF español aleatorio
function generarNIF() {
    var numero = Math.floor(Math.random() * 1000000000);
    numero = numero.toString();
    numero = numero.substring(0, 8);
    var letra = 'TRWAGMYFPDXBNJZSQVHLCKE'[numero % 23]; // Cálculo del dígito de control
    return numero.toString() + letra;
}

function generarNIE() {
    // Selección aleatoria de la letra inicial (X, Y, o Z)
    var letraInicial = ['X', 'Y', 'Z'][Math.floor(Math.random() * 3)];

    // Generar un número aleatorio de 7 dígitos y asegurarse de que tenga 7 dígitos
    var numero = Math.floor(Math.random() * 10000000).toString();
    while (numero.length < 7) {
        numero = '0' + numero;
    }

    // Asignar el prefijo numérico según la letra inicial
    var prefijo;
    if (letraInicial === 'X') {
        prefijo = '0';
    } else if (letraInicial === 'Y') {
        prefijo = '1';
    } else { // Z
        prefijo = '2';
    }

    // Calcular el dígito de control
    var numeroCompleto = prefijo + numero;
    var letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
    var letraControl = letras[parseInt(numeroCompleto) % 23];

    return letraInicial + numero + letraControl;
}

function generarCIF() {
    const letras = 'ABCDEFGHJKLMNPQRSUVW';
    const letraInicial = letras.charAt(Math.floor(Math.random() * letras.length));

    const numeros = Array.from({ length: 7 }, () => Math.floor(Math.random() * 10)).join('');

    let sumaPares = 0;
    let sumaImpares = 0;

    for (let i = 0; i < numeros.length; i++) {
        const digito = parseInt(numeros[i], 10);
        if ((i + 1) % 2 === 0) {
            sumaPares += digito;
        } else {
            const doble = digito * 2;
            sumaImpares += doble < 10 ? doble : doble - 9;
        }
    }

    let sumaTotal = sumaPares + sumaImpares;
    let ultimoDigitoSuma = sumaTotal % 10;
    let digitoControl = ultimoDigitoSuma === 0 ? 0 : 10 - ultimoDigitoSuma;

    let control;
    if ('ABEH'.includes(letraInicial)) {
        // Para estas letras, el dígito de control es un número
        control = digitoControl;
    } else if ('KPQS'.includes(letraInicial)) {
        // Para estas letras, el dígito de control es una letra
        control = 'JABCDEFGHI'[digitoControl];
    } else {
        // Para el resto, puede ser número o letra
        const esNumero = Math.random() < 0.5;
        control = esNumero ? digitoControl : 'JABCDEFGHI'[digitoControl];
    }

    return letraInicial + numeros + control;
}

function copiNif(){
    var input = $('#hddCopiNif')[0];
    input.select();
    document.execCommand("copy");
    $("#alertCopiItem").fadeIn().delay(2000).fadeOut();
    setTimeout(function() {
        $("#alertCopiItem").hide(300,'');
    }, 3000);
}

function copiNie(){
    var input = $('#hddCopiNie')[0];
    input.select();
    document.execCommand("copy");
    $("#alertCopiItem").fadeIn().delay(2000).fadeOut();
    setTimeout(function() {
        $("#alertCopiItem").hide(300,'');
    }, 3000);
}

function copiCif(){
    var input = $('#hddCopiCif')[0];
    input.select();
    document.execCommand("copy");
    $("#alertCopiItem").fadeIn().delay(2000).fadeOut();
    setTimeout(function() {
        $("#alertCopiItem").hide(300,'');
    }, 3000);
}

function validateSpanishID(id) {
    id = id.toUpperCase(); // Normalize to uppercase for consistent validation
  
    const letters = "TRWAGMYFPDXBNJZSQVHLCKE"; // Lookup string for DNI/NIE control letter
  
    // 1. General format validation: 9 alphanumeric characters
    if (!/^[A-Z0-9]{9}$/.test(id)) {
      return { valid: false, type: 'Unknown' };
    }
  
    // 2. DNI validation (8 numbers + 1 letter)
    if (/^[0-9]{8}[A-Z]$/.test(id)) {
      const number = parseInt(id.slice(0, 8), 10);
      const letter = id[8];
      return { valid: letter === letters[number % 23], type: 'NIF' };
    }
  
    // 3. NIE validation (X, Y, Z followed by 7 numbers and 1 letter)
    if (/^[XYZ][0-9]{7}[A-Z]$/.test(id)) {
      const replacement = { X: "0", Y: "1", Z: "2" };
      const nieNumber = replacement[id[0]] + id.slice(1, 8);
      const letter = id[8];
      return { valid: letter === letters[parseInt(nieNumber, 10) % 23], type: 'NIE' };
    }
  
    // 4. CIF validation (1 letter + 7 numbers + 1 letter/number)
    if (/^[ABCDEFGHJNPQRSUVW][0-9]{7}[A-Z0-9]$/.test(id)) {
      let sumEven = 0;
      let sumOdd = 0;
      const firstLetter = id[0];
      const lastChar = id[8];
  
      for (let i = 1; i < 8; i++) {
        const digit = parseInt(id[i], 10);
        if (i % 2 === 0) { // Even positions
          sumEven += digit;
        } else { // Odd positions
          let doubledDigit = digit * 2;
          sumOdd += (doubledDigit < 10) ? doubledDigit : (Math.floor(doubledDigit / 10) + (doubledDigit % 10));
        }
      }
  
      const totalSum = sumEven + sumOdd;
      const controlDigit = (10 - (totalSum % 10)) % 10;
  
      let expectedControlChar;
      if ("ABEH".includes(firstLetter)) {
        expectedControlChar = String(controlDigit);
      } else if ("KPQS".includes(firstLetter)) {
        expectedControlChar = "JABCDEFGHI"[controlDigit];
      } else { 
        expectedControlChar = String(controlDigit);
        const alternativeControlChar = "JABCDEFGHI"[controlDigit];
        if (lastChar === alternativeControlChar) {
          return { valid: true, type: 'CIF' };
        }
      }
  
      return { valid: lastChar === expectedControlChar, type: 'CIF' };
    }
  
    return { valid: false, type: 'Unknown' };
}

$(document).ready(function() {

    $("#alerta .close").click(function() {
        $("#alerta").hide();
    });

    $('#divGenerateNif').click(function() {
        let nif = generarNIF();
        $('#divCopiNif').text(" " + nif);
        $('#hddCopiNif').val(nif);
        let nuevoElemento = $('<li> - '+nif+'</li>');
        $('#ulHistorialNif').prepend(nuevoElemento);
    });

    $('#divCopiNif').click(function() {
        let nif = copiNif();
    });

    //-------------------------------
    $('#divGenerateNie').click(function() {
        let nie = generarNIE();
        $('#divCopiNie').text(" " + nie);
        $('#hddCopiNie').val(nie);
        let nuevoElemento = $('<li> - '+nie+'</li>');
        $('#ulHistorialNie').prepend(nuevoElemento);
    });

    $('#divCopiNie').click(function() {
        let nif = copiNie();
    });

    //-------------------------------
    $('#divGenerateCif').click(function() {
        let cif = generarCIF();
        $('#divCopiCif').text(" " + cif);
        $('#hddCopiCif').val(cif);
        let nuevoElemento = $('<li> - '+cif+'</li>');
        $('#ulHistorialCif').prepend(nuevoElemento);
    });

    $('#divCopiCif').click(function() {
        let nif = copiNie();
    });

    //-------------------------------
    // Validators
    //-------------------------------
    $('#validateNifButton').click(function() {
        let nif = $('#nifValidatorInput').val();
        let result = validateSpanishID(nif);
        if (result.valid && result.type === 'NIF') {
            $('#nifValidationResult').text('NIF válido.').removeClass('text-danger').addClass('text-success');
        } else {
            $('#nifValidationResult').text('NIF no válido.').removeClass('text-success').addClass('text-danger');
        }
    });

    $('#validateNieButton').click(function() {
        let nie = $('#nieValidatorInput').val();
        let result = validateSpanishID(nie);
        if (result.valid && result.type === 'NIE') {
            $('#nieValidationResult').text('NIE válido.').removeClass('text-danger').addClass('text-success');
        } else {
            $('#nieValidationResult').text('NIE no válido.').removeClass('text-success').addClass('text-danger');
        }
    });

    $('#validateCifButton').click(function() {
        let cif = $('#cifValidatorInput').val();
        let result = validateSpanishID(cif);
        if (result.valid && result.type === 'CIF') {
            $('#cifValidationResult').text('CIF válido.').removeClass('text-danger').addClass('text-success');
        } else {
            $('#cifValidationResult').text('CIF no válido.').removeClass('text-success').addClass('text-danger');
        }
    });

});
