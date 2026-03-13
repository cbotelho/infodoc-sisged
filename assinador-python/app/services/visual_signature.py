# -*- coding: utf-8 -*-
"""
visual_signature.py - Aparência visual da assinatura no PDF
"""

import logging
from datetime import datetime
from typing import Dict, Optional, Tuple

import PyPDF2
from PyPDF2 import PdfReader, PdfWriter
from PyPDF2.generic import (
    NameObject, TextStringObject, NumberObject,
    ArrayObject, DictionaryObject, IndirectObject
)

logger = logging.getLogger(__name__)

class VisualSignatureRenderer:
    """
    Renderiza a aparência visual da assinatura no PDF
    """
    
    @staticmethod
    def create_visual_appearance(cert_info: Dict,
                                  x_pt: float,
                                  y_pt: float,
                                  width_pt: float,
                                  height_pt: float) -> Dict:
        """
        Cria dicionário de aparência visual
        
        Args:
            cert_info: Informações do certificado
            x_pt: Posição X em pontos
            y_pt: Posição Y em pontos
            width_pt: Largura em pontos
            height_pt: Altura em pontos
            
        Returns:
            Dicionário de aparência
        """
        # Nome do titular
        subject = cert_info.get('subject', {})
        name = subject.get('commonName', 'Assinante')
        
        # Texto da assinatura
        lines = [
            f"Assinado digitalmente por: {name}",
            f"Data: {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}",
            f"ICP-Brasil: {'Sim' if cert_info.get('is_icp_brasil') else 'Não'}"
        ]
        
        # Criar stream de aparência (simplificado)
        appearance = f"""
        BT
        /Helv 10 Tf
        0 0 0 rg
        2 {height_pt-10} Td
        ({lines[0]}) Tj
        0 -12 Td
        ({lines[1]}) Tj
        0 -12 Td
        ({lines[2]}) Tj
        ET
        """
        
        return {
            'type': 'text',
            'lines': lines,
            'position': (x_pt, y_pt, width_pt, height_pt)
        }
    
    @staticmethod
    def add_visual_to_pdf(writer: PdfWriter,
                          page_num: int,
                          cert_info: Dict,
                          x_mm: float,
                          y_mm: float,
                          width_mm: float,
                          height_mm: float) -> None:
        """
        Adiciona aparência visual diretamente no PDF
        
        Args:
            writer: PdfWriter
            page_num: Número da página
            cert_info: Informações do certificado
            x_mm: Posição X em mm
            y_mm: Posição Y em mm
            width_mm: Largura em mm
            height_mm: Altura em mm
        """
        # Converter mm para pontos
        x_pt = x_mm * 72 / 25.4
        y_pt = y_mm * 72 / 25.4
        width_pt = width_mm * 72 / 25.4
        height_pt = height_mm * 72 / 25.4
        
        # Texto da assinatura
        subject = cert_info.get('subject', {})
        name = subject.get('commonName', subject.get('organizationName', 'Assinante'))
        
        lines = [
            f"Assinado digitalmente por: {name}",
            f"Data: {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}",
            f"ICP-Brasil: {'Sim' if cert_info.get('is_icp_brasil') else 'Não'}"
        ]
        
        # Criar conteúdo da aparência (simplificado)
        content = f"""
        q
        0.5 w
        0 0 1 RG
        {x_pt} {y_pt} {width_pt} {height_pt} re
        S
        0 0 0 rg
        /F1 {height_pt/5} Tf
        BT
        {x_pt+5} {y_pt+height_pt-8} Td
        ({lines[0]}) Tj
        0 -12 Td
        ({lines[1]}) Tj
        0 -12 Td
        ({lines[2]}) Tj
        ET
        Q
        """
        
        # Adicionar conteúdo à página
        if "/Contents" not in writer.pages[page_num]:
            writer.pages[page_num][NameObject("/Contents")] = ArrayObject()
        
        # Criar stream de conteúdo
        stream = PyPDF2.generic.TextStringObject(content)
        writer.pages[page_num]["/Contents"].append(stream)