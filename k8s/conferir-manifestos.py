#!/usr/bin/env python3
"""Confere os manifestos do Kubernetes antes de alguém aplicar num cluster.

Não substitui o kubectl. Pega o que costuma passar em revisão e só aparece em
produção: container rodando como root, deployment sem limite de recurso,
sonda faltando, imagem em latest e segredo com valor preenchido no arquivo.
"""

import sys
from pathlib import Path

import yaml

PASTA = Path(__file__).parent


def documentos():
    for arquivo in sorted(PASTA.glob('*.yaml')):
        with arquivo.open(encoding='utf-8') as aberto:
            for documento in yaml.safe_load_all(aberto):
                if documento:
                    yield arquivo.name, documento


def conferir(arquivo, documento, reclamar):
    tipo = documento.get('kind')
    nome = documento.get('metadata', {}).get('name', '?')
    onde = f'{arquivo} ({tipo} {nome})'

    if tipo == 'Secret':
        for chave, valor in (documento.get('stringData') or {}).items():
            if valor:
                reclamar(f'{onde}: {chave} tem valor gravado no arquivo')

    if 'Ingress' == tipo and not documento['spec'].get('tls'):
        reclamar(f'{onde}: entrada HTTP sem TLS')

    if tipo not in ('Deployment', 'StatefulSet', 'DaemonSet', 'CronJob'):
        return

    # No CronJob o pod fica dois níveis mais fundo, e é justamente por isso
    # que ele costuma escapar da revisão: é o mesmo container rodando sem
    # ninguém olhando, de madrugada.
    especificacao = (
        documento['spec']['jobTemplate']['spec']['template']['spec']
        if 'CronJob' == tipo
        else documento['spec']['template']['spec']
    )

    if 'CronJob' == tipo and 'Forbid' != documento['spec'].get('concurrencyPolicy'):
        reclamar(f'{onde}: sem concurrencyPolicy Forbid, duas execuções podem se sobrepor')

    if not especificacao.get('securityContext', {}).get('runAsNonRoot'):
        reclamar(f'{onde}: não declara runAsNonRoot')

    for container in especificacao.get('containers', []):
        rotulo = f"{onde}, container {container['name']}"
        imagem = container.get('image', '')

        if ':' not in imagem or imagem.endswith(':latest'):
            reclamar(f'{rotulo}: imagem sem versão fixa ({imagem})')

        recursos = container.get('resources', {})
        if not recursos.get('requests') or not recursos.get('limits'):
            reclamar(f'{rotulo}: sem requests ou sem limits')

        contexto = container.get('securityContext', {})
        if contexto.get('allowPrivilegeEscalation') is not False:
            reclamar(f'{rotulo}: permite elevação de privilégio')

        # O worker de fila não atende porta: sonda nele não faz sentido.
        atende_porta = bool(container.get('ports'))
        if atende_porta and not container.get('readinessProbe'):
            reclamar(f'{rotulo}: atende porta e não tem readinessProbe')
        if atende_porta and not container.get('livenessProbe'):
            reclamar(f'{rotulo}: atende porta e não tem livenessProbe')


def main() -> int:
    reclamacoes = []
    vistos = 0

    for arquivo, documento in documentos():
        vistos += 1
        conferir(arquivo, documento, reclamacoes.append)

    if reclamacoes:
        print(f'{len(reclamacoes)} problema(s) nos manifestos:\n')
        for reclamacao in reclamacoes:
            print(f'  - {reclamacao}')
        return 1

    print(f'{vistos} manifesto(s) conferido(s), nada a apontar.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
