$(document).ready(function () {
    $('#scheduled-form').hide(); // Oculta o elemento por padrão
    $('#recurrent-form').hide(); // Oculta o elemento por padrão
    $('#select-day-week').hide(); // Oculta o elemento por padrão
    $('#select-day-month').hide(); // Oculta o elemento por padrão

    $('#tab-immediate-payment').on('shown.bs.tab', function () {
        $('#tab-immediate-payment').addClass('active');
    });

    $('#tab-immediate-payment').on('hidden.bs.tab', function () {
        $('#tab-immediate-payment').removeClass('active');
    });

    $('#tab-scheduled-payment').on('shown.bs.tab', function () {
        $('#tab-scheduled-payment').addClass('active');
        $('#scheduled-form').show(); // Exibe o elemento quando a aba é selecionada
        $('#scheduling-date').prop('disabled', false);
    });

    $('#tab-scheduled-payment').on('hidden.bs.tab', function () {
        $('#scheduled-form').hide(); // Oculta o elemento quando a aba é desselecionada
        $('#tab-scheduled-payment').removeClass('active');
        $('#scheduling-date').prop('disabled', true);
    });

    $('#tab-recurrent-payment').on('shown.bs.tab', function () {
        $('#tab-recurrent-payment').addClass('active')
        $('#recurrent-form').show(); // Exibe o elemento quando a aba é selecionada
        $('#recurrency-start-date').prop('disabled', false);
        $('#amountRecurrence').prop('disabled', false);
        $('#recurrence-type').prop('disabled', false);
    });

    $('#tab-recurrent-payment').on('hidden.bs.tab', function () {
        $('#recurrent-form').hide(); // Oculta o elemento quando a aba é desselecionada
        $('#tab-recurrent-payment').removeClass('active')
        $('#recurrency-start-date').prop('disabled', true);
        $('#amountRecurrence').prop('disabled', true);
        $('#recurrence-type').prop('disabled', true);
    });

    $('#recurrence-type').on('change', function () {
        if ($(this).val() === 'semanal') {
            $('#select-day-week').show();
            $('#day-week').prop('disabled', false);
        } else if ($(this).val() === 'mensal') {
            $('#select-day-month').show();
            $('#select-day-week').hide();
            $('#day-month').prop('disabled', false);
        } else {
            $('#select-day-week').hide();
            $('#select-day-month').hide();
            $('#day-week').prop('disabled', true);
            $('#day-month').prop('disabled', true);
        }
    });

    const $form = $('#form-info');

    const $btnSubmitForm = $('#generate-pix-open-finance');
    const $modalRedirect = $("#modal-redirect");
    const $modalResult = $("#modalResult");

    $btnSubmitForm.click(function () {
        // INFORMAÇÕES GERAIS DO FORM
        const $payerInformationInput = $("#payerInformation");
        const $valueInput = $("#value");
        const $cpfInput = $("#cpf");
        const $institutionChosenInput = $('input[name=radio-participants-institutions]');

        const payerInformation = $payerInformationInput.val();
        const value = $valueInput.val();
        const cpf = $cpfInput.val();
        const institutionChosen = $institutionChosenInput.filter(':checked').val();

        var selectedPaymentType;
        if ($('#tab-immediate-payment').hasClass('active')) {
            selectedPaymentType = 'immediate-payment';
        } else if ($('#tab-scheduled-payment').hasClass('active')) {
            selectedPaymentType = 'scheduled-payment';
        } else if ($('#tab-recurrent-payment').hasClass('active')) {
            selectedPaymentType = 'recurrent-payment';
        }

        // Verifica se o formulário foi preenchido
        if ($form[0].checkValidity()) {
            // Valida o tipo de pagamento escolhido (imediato/agendado/recorrente)
            if (selectedPaymentType === 'immediate-payment') {
                $btnSubmitForm.html('Processando a requisição <span id="icon-load" class="spinner-border spinner-border-sm">');
                $btnSubmitForm.prop('disabled', true);

                $.ajax({
                    url: "../pix-open-finance/emitir_pix_open_finance.php",
                    data: { payerInformation, value, cpf, institutionChosen, selectedPaymentType },
                    type: 'post',
                    dataType: 'json',
                    success: function (resposta) {
                        if (resposta.code === 200) {
                            showChosenBank();
                            $modalRedirect.modal('show');
                            setTimeout(() => {
                                const html = `<label class="form-label"><b>Identificador do pagamento(<em>identificadorPagamento</em>)</b></label><input class="form-control" type="text" value="${resposta.identificadorPagamento}"
								disabled><label class="form-label mt-4"><b>Link para o pagamento (<em>redirectURI</em>)</b></label><p>Se o link para pagamento não abrir automaticamente, clique no botão abaixo.<p/><a style="width: 100%; display: flex; justify-content: center;align-items: center;" type="button" target="_blank" href="${resposta.redirectURI}" class="btn btn-efi-blue icon-success">Acessar página de pagamento <i class="bi bi-box-arrow-up-right"></i></a>`;
                                $("#result_table").html(html);
                                $modalRedirect.modal('hide');
                                $modalResult.modal('show');
                                abrirLinkNovaAba(resposta.redirectURI);
                                $btnSubmitForm.prop('disabled', false);
                                $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg"> ');
                            }, 5000);
                        } else {
                            $btnSubmitForm.prop('disabled', false);
                            $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg">');
                            setTimeout(() => {
                                alert(`Código: ${resposta.code}\nPropriedade: ${resposta.error}\nDescrição: ${resposta.errorDescription}`);
                            }, 10);
                        }
                    },
                    error: function (resposta) {
                        resposta = resposta.responseJSON;
                        $btnSubmitForm.prop('disabled', false);
                        $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg"> ');
                        setTimeout(() => {
                            alert(`Ocorreu um erro - Mensagem: ${resposta.message}`);
                        }, 10);
                    }
                });
            } else if (selectedPaymentType === 'scheduled-payment') {
                $btnSubmitForm.prop('disabled', true);
                $btnSubmitForm.html('Processando a requisição <span id="icon-load" class="spinner-border spinner-border-sm">');

                // CAPTURA INFORMAÇÕES SE O PAGAMENTO FOR DO TIPO AGENDADO
                const schedulingDate = $("#scheduling-date").val();

                $.ajax({
                    url: "../pix-open-finance/emitir_pix_open_finance.php",
                    data: { payerInformation, value, cpf, institutionChosen, schedulingDate, selectedPaymentType },
                    type: 'post',
                    dataType: 'json',
                    success: function (resposta) {
                        if (resposta.code === 200) {
                            showChosenBank();
                            $modalRedirect.modal('show');
                            setTimeout(() => {
                                const html = `<label class="form-label"><b>Identificador do pagamento(<em>identificadorPagamento</em>)</b></label><input class="form-control" type="text" value="${resposta.identificadorPagamento}"
								disabled><label class="form-label mt-4"><b>Link para o pagamento (<em>redirectURI</em>)</b></label><p>Se o link para pagamento não abrir automaticamente, clique no botão abaixo.<p/><a style="width: 100%; display: flex; justify-content: center;align-items: center;" type="button" target="_blank" href="${resposta.redirectURI}" class="btn btn-efi-blue icon-success">Acessar página de pagamento <i class="bi bi-box-arrow-up-right"></i></a>`;
                                $("#result_table").html(html);
                                $modalRedirect.modal('hide');
                                $modalResult.modal('show');
                                $btnSubmitForm.prop('disabled', false);
                                $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg"> ');
                                abrirLinkNovaAba(resposta.redirectURI);
                            }, 5000);
                        } else {
                            $btnSubmitForm.prop('disabled', false);
                            $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg">');
                            setTimeout(() => {
                                alert(`Código: ${resposta.code}\nPropriedade: ${resposta.error}\nDescrição: ${resposta.errorDescription}`);
                            }, 10);
                        }
                    },
                    error: function (resposta) {
                        resposta = resposta.responseJSON;
                        $btnSubmitForm.prop('disabled', false);
                        $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg"> ');
                        setTimeout(() => {
                            alert(`Ocorreu um erro - Mensagem: ${resposta.message}`);
                        }, 10);
                    }
                });
            } else if (selectedPaymentType === 'recurrent-payment') {
                $btnSubmitForm.prop('disabled', true);
                $btnSubmitForm.html('Processando a requisição <span id="icon-load" class="spinner-border spinner-border-sm">');

                // CAPTURA INFORMAÇÕES SE O PAGAMENTO FOR DO TIPO RECORRENTE
                const paymentStartDate = $("#recurrency-start-date").val();
                const amountRecurrence = $("#amountRecurrence").val();
                const recurrenceType = $("#recurrence-type").val();
                const dayOfWeek = (recurrenceType === 'semanal') ? $("#day-week").val() : null;
                const dayOfMonth = (recurrenceType === 'mensal') ? $("#day-month").val() : null;

                $.ajax({
                    url: "../pix-open-finance/emitir_pix_open_finance.php",
                    data: { payerInformation, value, cpf, institutionChosen, selectedPaymentType, paymentStartDate, amountRecurrence, recurrenceType, dayOfWeek, dayOfMonth },
                    type: 'post',
                    dataType: 'json',
                    success: function (resposta) {
                        if (resposta.code === 200) {
                            showChosenBank();
                            $modalRedirect.modal('show');
                            setTimeout(() => {
                                const html = `<label class="form-label"><b>Identificador do pagamento(<em>identificadorPagamento</em>)</b></label><input class="form-control" type="text" value="${resposta.identificadorPagamento}"
								disabled><label class="form-label mt-4"><b>Link para o pagamento (<em>redirectURI</em>)</b></label><p>Se o link para pagamento não abrir automaticamente, clique no botão abaixo.<p/><a style="width: 100%; display: flex; justify-content: center;align-items: center;" type="button" target="_blank" href="${resposta.redirectURI}" class="btn btn-efi-blue icon-success">Acessar página de pagamento <i class="bi bi-box-arrow-up-right"></i></a>`;
                                $("#result_table").html(html);
                                $modalRedirect.modal('hide');
                                $modalResult.modal('show');
                                abrirLinkNovaAba(resposta.redirectURI);
                                $btnSubmitForm.prop('disabled', false);
                                $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg"> ');
                            }, 5000);
                        } else {
                            $btnSubmitForm.prop('disabled', false);
                            $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg">');
                            setTimeout(() => {
                                alert(`Código: ${resposta.code}\nPropriedade: ${resposta.error}\nDescrição: ${resposta.errorDescription}`);
                            }, 10);
                        }
                    },
                    error: function (resposta) {
                        resposta = resposta.responseJSON;
                        $btnSubmitForm.prop('disabled', false);
                        $btnSubmitForm.html('Pagar com meu banco <img src = "../assets/img/open-finance-ico.svg"> ');
                        setTimeout(() => {
                            alert(`Ocorreu um erro - Mensagem: ${resposta.message}`);
                        }, 10);
                    }
                });
            } else {
                alert("Escolha um método de pagamento válido.");
            }
        } else {
            alert("Você deverá preencher todos os dados do formulário.");
        }
    });
});

function showChosenBank() {
    var idParticipant = $('input[name=radio-participants-institutions]:checked').val()
    $('#institutionChosen').val(idParticipant);


    var institutionName = $('#nome_' + idParticipant).html();
    var institutionLogo = $('#img_' + idParticipant).attr('src');

    if (typeof institutionName !== 'undefined') {
        var institutionChosen = `
      <div class="p-2">
        <img style="height: 32px;" src="${institutionLogo}" class="img-fluid" >
        ${institutionName}
      </div>`;

        $('#div-institution-chosen').html(institutionChosen);

        var modalInstitutionChosen = `
      <img class="float-start" style="height: 50px;" src="${institutionLogo}">
      <p style="font-weight: bold;" class="ms-2 mt-3 text-dark">${institutionName}</p>
      `;

        $('#modal-institution-chosen').html(modalInstitutionChosen);
    }
}

function abrirLinkNovaAba(url) {
    // Tenta abrir o link em uma nova aba
    let novaAba = window.open(url, '_blank');

    // Verifica se a nova aba foi bloqueada
    if (!novaAba || novaAba.closed || typeof novaAba.closed === 'undefined') {
        // Exibe uma confirmação ao cliente
        const confirmacao = confirm('Deseja abrir a página de pagamento agora?');

        if (confirmacao) {
            // Se o cliente confirmar, tenta abrir novamente
            novaAba = window.open(url, '_blank');

            // Caso o navegador bloqueie novamente, abre na mesma aba
            if (!novaAba) {
                window.location.href = url;
            }
        }
    } else  {
        // Se a nova aba foi aberta com sucesso, define a URL nela
        novaAba.location.href = url;
    }
}