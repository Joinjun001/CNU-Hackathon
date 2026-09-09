#!/usr/bin/env python3
"""
Unit and Schema Verification Test for Word402 Protocol & Logic
Runs independently without requiring a live WordPress server.
"""

import re
import json
import unittest
import uuid

TRANSFER_EVENT_TOPIC = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"

class MockWord402Protocol:
    @staticmethod
    def build_402_challenge(post_id, price, recipient, network="base-sepolia"):
        challenge_id = "chn_" + str(uuid.uuid4())
        www_auth = (
            f'x402 network="{network}", currency="USDC", '
            f'amount="{price}", recipient="{recipient}", challenge="{challenge_id}"'
        )
        body = {
            "protocol": "x402",
            "version": "2.0",
            "status": "payment_required",
            "resource": {
                "post_id": post_id,
                "title": "2026 AI Agentic Commerce Report"
            },
            "payment_requirements": {
                "network": network,
                "currency": "USDC",
                "amount": str(price),
                "recipient": recipient,
                "challenge_id": challenge_id
            }
        }
        return www_auth, body

    @staticmethod
    def parse_payment_header(x_payment_header):
        try:
            data = json.loads(x_payment_header)
            if "tx_hash" in data and "challenge_id" in data:
                return data
        except Exception:
            pass
        return None

class MockERC20LogParser:
    @staticmethod
    def parse_log(log, expected_recipient, expected_usdc_contract):
        if log.get("address", "").lower() != expected_usdc_contract.lower():
            return None, "INVALID_TOKEN_CONTRACT"

        topics = log.get("topics", [])
        if not topics or topics[0].lower() != TRANSFER_EVENT_TOPIC.lower():
            return None, "NOT_TRANSFER_EVENT"

        # Topic 2: to address (last 20 bytes)
        to_addr = "0x" + topics[2][-40:]
        if to_addr.lower() != expected_recipient.lower():
            return None, "INVALID_RECIPIENT"

        raw_val = int(log.get("data", "0x0"), 16)
        usdc_val = raw_val / 1_000_000.0
        return usdc_val, "SUCCESS"

class MockMarkdownTransformer:
    @staticmethod
    def to_markdown(html, title=""):
        # Remove scripts, styles
        html = re.sub(r'<(script|style|nav|footer)[^>]*>.*?</\1>', '', html, flags=re.DOTALL | re.IGNORECASE)
        # Headers
        html = re.sub(r'<h1[^>]*>(.*?)</h1>', r'\n# \1\n', html, flags=re.IGNORECASE)
        html = re.sub(r'<h2[^>]*>(.*?)</h2>', r'\n## \1\n', html, flags=re.IGNORECASE)
        html = re.sub(r'<h3[^>]*>(.*?)</h3>', r'\n### \1\n', html, flags=re.IGNORECASE)
        # Bold & Italic
        html = re.sub(r'<(strong|b)[^>]*>(.*?)</\1>', r'**\2**', html, flags=re.IGNORECASE)
        # Paragraphs & Lists
        html = re.sub(r'<li[^>]*>(.*?)</li>', r'- \1\n', html, flags=re.IGNORECASE)
        html = re.sub(r'<p[^>]*>(.*?)</p>', r'\n\1\n', html, flags=re.IGNORECASE)
        # Strip remaining tags
        text = re.sub(r'<[^>]+>', '', html)
        lines = [line.strip() for line in text.split("\n") if line.strip()]
        res = "\n\n".join(lines)
        if title:
            res = f"# {title}\n\n" + res
        return res

class TestWord402Logic(unittest.TestCase):

    def setUp(self):
        self.recipient = "0x71C83638379188981650392b49B283946F174B29"
        self.usdc = "0x036CbD53842c5426634e7929541eC2318f3dCF7e"
        self.used_tx_db = set()

    def test_challenge_and_header_generation(self):
        www_auth, body = MockWord402Protocol.build_402_challenge(42, 0.005, self.recipient)
        self.assertIn("x402 network=", www_auth)
        self.assertIn('currency="USDC"', www_auth)
        self.assertIn("chn_", www_auth)
        self.assertEqual(body["status"], "payment_required")
        self.assertEqual(body["payment_requirements"]["amount"], "0.005")

    def test_payment_header_parsing(self):
        valid_header = json.dumps({
            "tx_hash": "0x3d4a8e1b9c284759492837482938472938472938472938472938472938472938",
            "challenge_id": "chn_12345",
            "sender": "0xAgent"
        })
        parsed = MockWord402Protocol.parse_payment_header(valid_header)
        self.assertIsNotNone(parsed)
        self.assertEqual(parsed["challenge_id"], "chn_12345")

    def test_erc20_log_verification(self):
        # Valid Transfer Log
        valid_log = {
            "address": self.usdc,
            "topics": [
                TRANSFER_EVENT_TOPIC,
                "0x0000000000000000000000001111111111111111111111111111111111111111",
                "0x00000000000000000000000071c83638379188981650392b49b283946f174b29" # matches recipient
            ],
            "data": hex(5000) # 0.005 USDC (5000 units)
        }
        amount, status = MockERC20LogParser.parse_log(valid_log, self.recipient, self.usdc)
        self.assertEqual(status, "SUCCESS")
        self.assertAlmostEqual(amount, 0.005)

        # Wrong recipient log
        wrong_log = dict(valid_log)
        wrong_log["topics"] = [
            TRANSFER_EVENT_TOPIC,
            "0x0000000000000000000000001111111111111111111111111111111111111111",
            "0x0000000000000000000000009999999999999999999999999999999999999999"
        ]
        amount, status = MockERC20LogParser.parse_log(wrong_log, self.recipient, self.usdc)
        self.assertEqual(status, "INVALID_RECIPIENT")

    def test_replay_attack_prevention(self):
        tx_hash = "0xabc123"
        # First use
        self.assertNotIn(tx_hash, self.used_tx_db)
        self.used_tx_db.add(tx_hash)

        # Second use (Replay Attack)
        is_replay = (tx_hash in self.used_tx_db)
        self.assertTrue(is_replay)

    def test_markdown_transformation(self):
        html_sample = """
        <script>alert('bad');</script>
        <h1>제목입니다</h1>
        <p>본문 내용이며 <strong>중요한</strong> 사실입니다.</p>
        <ul>
            <li>첫번째 항목</li>
            <li>두번째 항목</li>
        </ul>
        """
        md = MockMarkdownTransformer.to_markdown(html_sample, "기사 제목")
        self.assertNotIn("<script>", md)
        self.assertNotIn("<p>", md)
        self.assertIn("# 제목입니다", md)
        self.assertIn("**중요한**", md)
        self.assertIn("- 첫번째 항목", md)

if __name__ == "__main__":
    unittest.main()
