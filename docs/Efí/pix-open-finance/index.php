<?php
$autoload = realpath(__DIR__ . '/../vendor/autoload.php');
if (!file_exists($autoload)) {
    die("Autoload file not found or on path <code>$autoload</code>.");
}

require_once $autoload;

use Efi\Exception\EfiException;
use Efi\EfiPay;

// Lê o arquivo json com suas credenciais
$file = file_get_contents(__DIR__ . '/../credentials.json');
$options = json_decode($file, true);

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Google Tag Manager -->
	<script>
		(function (w, d, s, l, i) {
			w[l] = w[l] || [];
			w[l].push({
				'gtm.start': new Date().getTime(),
				event: 'gtm.js'
			});
			var f = d.getElementsByTagName(s)[0],
				j = d.createElement(s),
				dl = l != 'dataLayer' ? '&l=' + l : '';
			j.async = true;
			j.src =
				'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
			f.parentNode.insertBefore(j, f);
		})(window, document, 'script', 'dataLayer', 'GTM-58FCSP');
	</script>
	<!-- End Google Tag Manager -->
	<meta charset="UTF-8">
	<!-- <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.css"> -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet"
		integrity="sha384-4bw+/aepP/YC94hEpVNVgiZdgIC5+VKNBQNGCHeKRQN+PtmoHDEXuppvnDJzQIu9" crossorigin="anonymous">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-HwwvtgBNo3bZJJLYd8oVXjrBZt8cqVSpeBNS5n7C8IVInixGAoxmnlMuBnhbgrkm"
		crossorigin="anonymous"></script>
	<link rel="stylesheet" href="../assets/bootstrap/css/style.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
	<script type="text/javascript" src="../assets/bootstrap/js/script-pix-open-finance.js"></script>
	<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<title>Exemplos Oficiais das APIs Efí </title>
	<link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon">
</head>

<body>
	<!-- Google Tag Manager (noscript) -->
	<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-58FCSP" height="0" width="0"
			style="display:none;visibility:hidden"></iframe></noscript>
	<!-- End Google Tag Manager (noscript) -->
	<nav class="navbar navbar-expand-md navbar-light bg-light">
		<div class="container-fluid navbar-efi pt-2 pb-3">
			<a class="navbar-brand" href="../">
				<img src="../assets/img/logo-efi-pay.svg" alt="Efí" width="90px">
			</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse"
				aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarCollapse">
				<ul class="navbar-nav col-lg-12 justify-content-md-end justify-content-lg-end ">
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle text-end" href="#" role="button" data-bs-toggle="dropdown"
							aria-expanded="false">
							Exemplos
						</a>
						<ul class="dropdown-menu">
							<li><a href="../boleto/">Boletos</a></li>
							<li><a href="../cartao/">Cartão</a></li>
							<li><a href="../pix/">Pix</a></li>
							<li><a href="../pix-open-finance/">Pix via Open Finance</a></li>
							<li><a href="../assinatura/">Assinaturas</a></li>
							<li><a href="../carne/">Carnê</a></li>
							<li><a href="../link-de-pagamento/">Link de Pagamento</a></li>
							<li><a href="../split-de-pagamento/">Split de Pagamento</a></li>
						</ul>
					</li>
					<div class="d-flex justify-content-md-between justify-content-sm-between">
						<a target="blank" class="btn btn-efi-blue"
							href="https://sejaefi.com.br/central-de-ajuda/efi-bank/como-abrir-conta-na-efi-bank#conteudo">Abra
							sua conta grátis</a>
						<a target="blank" class="btn btn-efi " href="https://app.sejaefi.com.br/">Acessar minha
							conta</a>
					</div>
				</ul>
			</div>
		</div>
	</nav>

	<div class="container-fluid content">
		<div class="row">
			<!-- Coluna da Imagem -->
			<div class="col d-flex align-items-start justify-content-start">
				<img src="../assets/img/api_pix.png" alt="Imagem" class="img-api" width="56" height="56">
				<h2 class="mt-3 ms-3">Pix via Open Finance</h2>
			</div>
		</div>
		<div class="col-lg-4 margin">
			<h4><strong>Forma de pagamento</strong></h4>
			<ul class="nav nav-tabs mt-3 active" id="myTabs">
				<li class="nav-item active" id="tab-immediate-payment">
					<a class="nav-link show active imediato" id="tab1-tab" data-bs-toggle="tab" href="#tab1" role="tab"
						aria-controls="tab1" aria-selected="true">Imediato</a>
				</li>
				<li class="nav-item" id="tab-scheduled-payment">
					<a class="nav-link agendado" id="tab2-tab" data-bs-toggle="tab" href="#tab2" role="tab"
						aria-controls="tab2" aria-selected="false">Agendado</a>
				</li>
				<li class="nav-item" id="tab-recurrent-payment">
					<a class="nav-link recorrente" id="tab3-tab" data-bs-toggle="tab" href="#tab3" role="tab"
						aria-controls="tab3" aria-selected="false">Recorrente</a>
				</li>
			</ul>
		</div>
		<div class="row">
			<div class="col-lg-9">
				<form id="form-info" method="POST">
					<div class="row">
						<div class="col-lg-4 mt-5 info-body">
							<h4><strong>Informações do produto</strong></h4>

							<div class="form-group mt-3 mb-4">
								<label for="payerInformation">Descrição: (<em class="atributo">infoPagador</em>)</label>
								<input required type="text" class="form-control mt-1 custom-input" id="payerInformation"
									value="Simulação pagamento Pix Open Finance Efí" disabled>
							</div>
							<div class="form-group">
								<label for="value">Valor: (<em class="atributo">valor</em>)</label>
								<input required type="text" class="form-control mt-1 custom-input" id="value"
									value="0.01" disabled>
							</div>

						</div>
						<div class="col-lg-4 mt-5 info-body">
							<div class="row">
								<h4><strong>Informações do cliente</strong></h4>

								<div class="form-group  mt-3">
									<label for="cpf">CPF: (<em class="atributo">cpf</em>)</label>
									<input required type="text" class="form-control mt-1 custom-input" id="cpf"
										placeholder="CPF (sem formatação)">
								</div>
							</div>

							<!-- OPÇÃO EXIBIDA SE ESCOLHIDO PAGAMENTO AGENDADO -->
							<div id="scheduled-form">
								<br>
								<div class="row">
									<h4><strong>Informações do pagamento</strong></h4>
									<div class="form-group  mt-3">
										<label for="scheduling-date">Data do agendamento: (<em
												class="atributo">dataAgendamento</em>)</label>
										<input required type="date" class="form-control mt-1 custom-input"
											id="scheduling-date" disabled>
									</div>
								</div>
							</div>

							<!-- OPÇÃO EXIBIDA SE ESCOLHIDO PAGAMENTO RECORRENTE -->
							<div id="recurrent-form">
								<br>
								<div class="row">
									<h4><strong>Informações do pagamento</strong></h4>
									<div class="form-group  mt-3">
										<label for="recurrency-start-date">Data de início: (<em
												class="atributo">dataInicio</em>)</label>
										<input required type="date" class="form-control mt-1 custom-input"
											id="recurrency-start-date" disabled>
									</div>
									<div class="form-group  mt-3">
										<label for="amountRecurrence">Quantidade: (<em
												class="atributo">quantidade</em>)</label>
										<input required type="number" min='2' max="60"
											class="form-control mt-1 custom-input" id="amountRecurrence" value="2">
									</div>
									<div class="form-group  mt-3">
										<label for="recurrence-type">Recorrência: (<em
												class="atributo">tipo</em>)</label>
										<select required class="form-control mt-1 custom-input" id="recurrence-type"
											disabled>
											<option value="diaria" selected>Diária</option>
											<option value="semanal">Semanal</option>
											<option value="mensal">Mensal</option>
										</select>
									</div>
									<div class="form-group  mt-3" id="select-day-week">
										<label for="day-week">Dia da semana: (<em
												class="atributo">diaDaSemana</em>)</label>
										<select required class="form-control mt-1 custom-input" id="day-week" disabled>
											<option value="">Selecione</option>
											<option value="DOMINGO">Domingo</option>
											<option value="SEGUNDA_FEIRA">Segunda</option>
											<option value="TERCA_FEIRA">Terça</option>
											<option value="QUARTA_FEIRA">Quarta</option>
											<option value="QUINTA_FEIRA">Quinta</option>
											<option value="SEXTA_FEIRA">Sexta</option>
											<option value="SABADO">Sábado</option>
										</select>
									</div>
									<div class="form-group  mt-3" id="select-day-month">
										<label for="day-month">Dia do mês: (<em class="atributo">diaDoMes</em>)</label>
										<input required type="number" min='1' max="31"
											class="form-control mt-1 custom-input" id="day-month" value="1" disabled>
									</div>
								</div>
							</div>

						</div>
						<div class="col-lg-4 mt-5 info-body">
							<h4><strong>Banco ou fintech</strong></h4>

							<?php
							try {
								$api = new EfiPay($options);
								$participants = $api->ofListParticipants($params = ["tipoPessoa" => "PF"]);
								?>

								<div id="campo" class="form-group mt-3">
									<label for="radio-participants-institutions">Banco que deseja pagar: (<em
											class="atributo">idParticipante</em>)</strong></label>
									<div class="d-flex flex-column text-start">
										<input type="text" id="inputPesquisaBanco" class="form-control mt-1 search"
											placeholder="Buscar instituição">
									</div>
									<div class="modal-body" style="max-height: 200px; overflow-y: auto;">
										<ul class="list-group list">
											<li class="list-group-item">
												<div style="margin: 10px 0 -12px 0;" class="form-check">
													<input class="form-check-input" type="radio"
														name="radio-participants-institutions"
														id="radio_ebbed125-5cd7-42e3-965d-2e7af8e3b7ae"
														value="ebbed125-5cd7-42e3-965d-2e7af8e3b7ae" required="">
													<label class="form-check-label d-flex flex-row justify-content-between"
														for="radio_ebbed125-5cd7-42e3-965d-2e7af8e3b7ae">
														<div class="image-parent">
															<img id="img_ebbed125-5cd7-42e3-965d-2e7af8e3b7ae"
																style="height: 32px;"
																src="https://gerencianet-pub-prod-1.s3.amazonaws.com/openbanking/efi-by-gn-512x512.svg"
																class="img-fluid">
														</div>
														<div class="text-end mb-1">
															<h6 style="color:#283048"
																id="nome_ebbed125-5cd7-42e3-965d-2e7af8e3b7ae"
																class="my-0 name">Efí Bank.</h6>
															<small class="text-muted">EFI S.A. - INSTITUICAO DE
																PAGAMENTO</small>
														</div>
													</label>
												</div>
											</li>
											<?php
											foreach ($participants["participantes"] as $banco) {
												if ($banco["nome"] !== 'Efí S.A.') {
													?>
													<li class="list-group-item">
														<div style="margin: 10px 0 -12px 0;" class="form-check">
															<input class="form-check-input" type="radio"
																name="radio-participants-institutions"
																id="radio_<?= $banco['identificador'] ?>"
																value="<?= $banco['identificador'] ?>" required>
															<label class="form-check-label d-flex flex-row justify-content-between"
																for="radio_<?= $banco['identificador'] ?>">
																<div class="image-parent">
																	<img id="img_<?= $banco['identificador'] ?>"
																		style="height: 32px;" src="<?= $banco["logo"]; ?>"
																		class="img-fluid">
																</div>
																<div class="text-end mb-1">
																	<h6 style="color:#283048"
																		id="nome_<?= $banco['identificador'] ?>" class="my-0 name">
																		<?= $banco["nome"] ?>
																	</h6>
																	<small
																		class="text-muted"><?= $banco["organizacoes"][0]['nome'] ?></small>
																</div>
															</label>
														</div>
													</li>

													<?php
												}
											}
											?>

										</ul>

									</div>
								</div>

								<input type="hidden" id="institutionChosen" name="institutionChosen">

								<?php
							} catch (EfiException $e) {
								?>
								<div class="form-group mt-3">
									<p><strong>Falha ao buscar as instituições</strong></p>
									<em class="atributo"><?= $e->errorDescription ?></em>
								</div>
								<?php
							}
							?>
						</div>
					</div>
					<div class="col-lg-12 mt-5 d-flex justify-content-end">
						<button id="generate-pix-open-finance" type="button" class="btn btn-efi-blue icon-success">Pagar
							com meu banco
							<img src="../assets/img/open-finance-ico.svg"></button>
					</div>
				</form>
			</div>

			<div class="col-lg-3">
				<div id="info-mobile" class="col-lg-12 content-guidelines">
					<div class="alert alert-info" role="alert">
						<img class="me-1 mb-1" src="../assets/img/info-circle.svg" />
						<b>Informação!</b><br><br>
						<p>Para uma melhor experiência no pagamento, sugerimos fazer a simulação em um dispositivo
							móvel.</p>
					</div>
				</div>

				<div style="margin-top: 10px;" class="col-lg-12 content-guidelines">
					<div class="alert alert-warning" role="alert">
						<img class="me-1 mb-1" src="../assets/img/exclamation-triangle-orange.svg" />
						<b>Atenção!</b><br><br>
						<p>Esta é uma aplicação que simula a iniciação de pagamento Pix via Open Finance e permite que
							você pague uma compra de forma rápida, fluida e segura.</p>
						<p>Você fará um <b>pagamento real</b>, mas não se preocupe, <strong>devolveremos seu dinheiro
								logo em seguida</strong>. E, nos casos de pagamento <b>agendado</b> ou
							<b>recorrência</b>, <b>cancelamos as transações</b> logo após concluir a simulação.
						</p>
						<p>Experimente essa jornada de pagamento Pix mais simples e eficiente!</p>
					</div>
				</div>

				<div class="col-lg-12 mt-2 d-flex justify-content-end">
					<a href="../download/exemplo-pix-open-finance.zip" id="download-button" class="btn btn-efi"><svg
							class="icon-download"></svg> Baixar
						este exemplo </a>
				</div>

			</div>
		</div>
	</div>


	<footer>
		<div class="container mt-4 mb-4">
			<div class="row ">
				<div class="col-lg-6 col-md-12 text-lg-start text-center mb-4 mb-lg-0">
					<span class="contact-title-efi">Efí Bank</span>
					<br>
					<span class="info_endereco">Av Paulista, 1337, Edifício Paulista 1 - Bela Vista, São Paulo, SP -
						01311-200, Brasil</span>
				</div>
				<div class="col-lg-6 col-md-12">
					<div class="row">
						<div class="col-6 col-md-3 col-sm-3" id="contact-box">
							<span class="contact-title">(11) 2394 2208</span>
							<div id="contact-box">
								<p>São Paulo e região</p>
							</div>
						</div>
						<div class="col-6 col-md-3 col-sm-3" id="contact-box">
							<span class="contact-title">0800 941 2343</span>
							<div id="contact-box">
								<p>Ligações de telefone fixo</p>
							</div>
						</div>
						<div class="col-6 col-md-3 col-sm-3" id="contact-box">
							<span class="contact-title">4000 1234</span>
							<div id="contact-box">
								<p>Regiões metropolitanas</p>
							</div>
						</div>
						<div class="col-6 col-md-3 col-sm-3" id="contact-box">
							<span class="contact-title">0800 940 0361</span>
							<div id="contact-box">
								<p>Ouvidoria</p>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row footer-redes">
				<div class="row">
					<div class="col-lg-6 col-md-12 text-center">
						<div class="row">
							<div class="col-md-2">
								<a href="https://sejaefi.com.br/efi-bank" target="_blank"><svg
										xmlns="http://www.w3.org/2000/svg" width="65.161" height="51.056"
										viewBox="0 0 65.161 51.056">
										<g id="Group_27158" data-name="Group 27158"
											transform="translate(-96 -5165.238)">
											<path id="Path_21451" data-name="Path 21451"
												d="M56.808,11.283l8.353-3.077V0L55.05,7.913a2.1,2.1,0,0,0-1.026,1.709,1.906,1.906,0,0,0,2.784,1.661M54.024,39.761H59.4V13.872H54.024Zm-24.374,0h5.373V29.8H48.455V24.911H35.023c-.1-4.054,2.638-6.5,6.4-6.5a8.916,8.916,0,0,1,5.129,1.661l3.419-3.859a13.52,13.52,0,0,0-8.4-2.833c-7.083,0-11.918,4.689-11.918,11.528ZM5.471,24.081c.782-3.517,3.566-5.666,7.424-5.666,3.908,0,6.5,2.149,6.985,5.666Zm19.929,2.2c0-5.959-4.3-12.9-12.5-12.9C5.666,13.384,0,18.806,0,26.621S5.715,40.249,13.433,40.249a13.774,13.774,0,0,0,11.186-5.666l-3.761-3.077a9.066,9.066,0,0,1-7.571,3.712,7.448,7.448,0,0,1-7.815-6.252H22.748A2.356,2.356,0,0,0,25.4,26.279"
												transform="translate(96 5165.238)" fill="#586475" />
											<path id="Path_21452" data-name="Path 21452"
												d="M105.38,141.781h-.3l2.538,6.168h1.244l-2.716-6.345a.915.915,0,0,0-.888-.635.952.952,0,0,0-.914.635l-2.715,6.345h1.218Zm-10.533,6.168h1.142v-6.98H94.847Zm3.426-3.274a2.023,2.023,0,0,0,2.157-1.954c0-1.066-.838-1.752-2.107-1.752h-2.97v1.016h2.817a.944.944,0,0,1,1.066.939.961.961,0,0,1-.965,1.015Zm11.879,3.274h1.142l-.051-6.295-.609.229,4.036,5.584a1.01,1.01,0,0,0,.812.482.7.7,0,0,0,.66-.761v-6.219H115l.051,6.3.609-.229-4.036-5.584a.932.932,0,0,0-.787-.482.718.718,0,0,0-.685.762Zm-7.234-1.4h4.67v-1.015h-4.67Zm20.229,1.4h1.447l-3.223-3.5,3.2-3.477H123.1l-2.64,2.919a.763.763,0,0,0,0,1.142Zm-4.671,0h1.142v-6.98h-1.142Zm-23.122,0H98.3c1.548,0,2.411-.761,2.411-1.929a2.227,2.227,0,0,0-2.437-2.081H95.354v1.015h3.071c.634,0,1.066.33,1.066.914,0,.659-.533,1.066-1.294,1.066H95.354Z"
												transform="translate(30.809 5068.345)" fill="#586475" />
										</g>
									</svg></a>
							</div>
							<div class="col mt-2 info text-lg-start text-center">
								© 2007-<span id="ano-atual"></span> • Efí - Instituição de Pagamento. Todos os direitos
								reservados.
								<br>Efí S.A. CNPJ: 09.089.356/0001-18
							</div>
						</div>
					</div>
					<div class="col-lg-6 col-md-12 justify-content-lg-end justify-content-center d-flex">
						<ul class="list-unstyled d-flex mt-3 mt-lg-0">
							<li class="ms-6"><a href="https://www.youtube.com/@sejaefi" target="_blank"><img
										src="../assets/img/rede1.svg" /></a></li>
							<li class="ms-6"><a href="https://www.instagram.com/sejaefi/" target="_blank"><img
										src="../assets/img/rede2.svg" /></a></li>
							<li class="ms-6"><a href="https://www.linkedin.com/company/sejaefi/" target="_blank"><img
										src="../assets/img/rede-3.svg" /></a></li>
							<li class="ms-6"><a href="https://www.facebook.com/sejaefi" target="_blank"><img
										src="../assets/img/rede-4.svg" /></a></li>
							<li class="ms-6"><a href="https://twitter.com/sejaefi" target="_blank"><img
										src="../assets/img/rede-5.svg" /></a></li>
							<li class="ms-6"><a href="https://www.tiktok.com/@sejaefi" target="_blank"><img
										src="../assets/img/rede-6.svg" /></a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</footer>

	<!-- Este componente é utilizando para exibir um alerta(modal) para o usuário aguardar as consultas via API.  -->
	<div class="modal fade" id="modalResult" tabindex="-1" role="dialog" aria-labelledby="modalResultLabel">
		<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="modalResultLabel">Retorno da inciação de pagamento Pix via Open Finance
					</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span
							aria-hidden="true"></span></button>
				</div>
				<div class="modal-body">
					<p>O link de pagamento pode expirar em diferentes tempos, dependendo da instituição. Na maioria dos
						casos, o prazo é de 5 minutos.</p>
					<!--div responsável por exibir o resultado da emissão do pix-->
					<div class="panel panel-success">
						<div class="panel-body" id="result_table">

						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Este componente é utilizando para exibir um alerta(modal) para o usuário aguardar para o redirecionamento para o banco escolhido  -->
	<div class="modal fade" id="modal-redirect" tabindex="-1" role="dialog" aria-labelledby="modal-redirectLabel">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Redirecionamento
						<div class="spinner-border spinner-border-sm ms-2 text-info" role="status">
							<span class="visually-hidden">Loading...</span>
						</div>
					</h5>
				</div>
				<div id="campo" class="modal-body">
					<div class="mb-3">
						<img style="height: 30px;" src="../assets/img/logo-open-banking.png">
						<p>Você está sendo redirecionado para a instituição iniciadora de pagamentos escolhida.</p>
					</div>

					<div class="shadow rounded-4 border-0">
						<ul class="list-group">
							<li style="background-color: #F6F8FC;" class="list-group-item d-flex flex-row p-2">
								<img style="width: 50px;" src="../assets/img/logo-efi-pay.svg">
								<p style="font-weight: bold;" class="ms-2 mt-3 text-dark">Efí Bank</p>
							</li>
							<li style="background-color: #ff7717; color: #F6F8FC;"
								class="list-group-item d-flex flex-row text-start p-3 pb-4">
								<svg xmlns="http://www.w3.org/2000/svg" style="color: #F6F8FC;" width="35" height="35"
									fill="currentColor" class="bi bi-arrow-down ms-1" viewBox="0 0 16 16">
									<path fill-rule="evenodd"
										d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1z" />
								</svg>
								<small class="ms-3 text-dark">Estamos direcionando você para o ambiente da iniciadora de
									pagamentos escolhida.</small>
							</li>
							<li id="modal-institution-chosen" style="background-color: #F6F8FC;"
								class="list-group-item d-flex flex-row p-2">
								<p>Ainda não foi escolhido um banco</p>
							</li>
						</ul>
					</div>

				</div>
			</div>
		</div>
	</div>
</body>

<script src='https://cdnjs.cloudflare.com/ajax/libs/list.js/1.5.0/list.min.js'></script>

<script>
	/** INÍCIO - script para busca das instituições participantes */
	var options = {
		valueNames: ['name']
	};
	var userList = new List('campo', options);
	/** FIM - script para busca das instituições participantes */

	/** INÍCIO - validações de inputs */
	const today = new Date();
	const minDate = new Date(today.setDate(today.getDate() + 1));
	const maxDate = new Date(today.setFullYear(today.getFullYear() + 1));

	function formatDate(date) {
		const year = date.getFullYear();
		const month = String(date.getMonth() + 1).padStart(2, '0'); // Mês começa em 0
		const day = String(date.getDate()).padStart(2, '0');
		return `${year}-${month}-${day}`;
	}

	document.getElementById("scheduling-date").value = formatDate(minDate);
	document.getElementById("scheduling-date").min = minDate.toISOString().slice(0, 10);
	document.getElementById("scheduling-date").max = maxDate.toISOString().slice(0, 10);

	document.getElementById("recurrency-start-date").value = formatDate(minDate);
	document.getElementById("recurrency-start-date").min = minDate.toISOString().slice(0, 10);
	document.getElementById("recurrency-start-date").max = maxDate.toISOString().slice(0, 10);
	/** FIM - validações de inputs */
</script>

<script>
	window.addEventListener("DOMContentLoaded", function (event) {
		Swal.fire({
			title: 'Exemplo de integração Efí',
			icon: 'info',
			html: 'Esta página é só uma demonstração. Fique à vontade para testar sem preocupações — <strong>nenhum dado será salvo</strong>. Se quiser, você também pode baixar o código fonte do exemplo e explorar.',
			showCloseButton: true,
			focusConfirm: true,
			confirmButtonText: '<img src="../assets/img/ok-mark.png">',
			confirmButtonColor: '#0BA1C2'
		});

		hideInfoOnMobile();
		insertCurrentYear();
	});

	function hideInfoOnMobile() {
		const infoMobile = document.getElementById('info-mobile');

		if (window.matchMedia("(max-width: 991px)").matches) {
			// Oculta o elemento em dispositivos móveis (tela menor que 768px)
			if (infoMobile) {
				infoMobile.style.display = 'none';
			}
		}
	}

	const amountRecurrenceInput = document.getElementById('amountRecurrence');
	amountRecurrenceInput.addEventListener('input', function () {
		const max = parseInt(amountRecurrenceInput.getAttribute('max'), 10);
		if (this.value > max) {
			this.value = max;
		}
	});

	const dayMonthInput = document.getElementById('day-month');
	dayMonthInput.addEventListener('input', function () {
		const max = parseInt(dayMonthInput.getAttribute('max'), 10);
		if (this.value > max) {
			this.value = max;
		}
	});

	function insertCurrentYear() {
		const currentYear = new Date().getFullYear(); // Obtém o ano atual
		const yearElement = document.getElementById('ano-atual');

		if (yearElement) {
			yearElement.textContent = currentYear; // Insere o ano no elemento
		}
	}

</script>

</html>