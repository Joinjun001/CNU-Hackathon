#!/usr/bin/env python3
"""
Word402 AI Agent Demo Client
Demonstrates autonomous HTTP 402 negotiation, on-chain Base Sepolia USDC payment, and data retrieval.
Uses standard library (urllib) by default for zero-dependency portability.
"""

import os
import sys
import json
import time
import argparse
import urllib.request
import urllib.error
import urllib.parse

# Optional web3 import for live on-chain transactions
try:
    from web3 import Web3
    from eth_account import Account
    WEB3_AVAILABLE = True
except ImportError:
    WEB3_AVAILABLE = False

ERC20_TRANSFER_ABI = [
    {
        "constant": False,
        "inputs": [
            {"name": "_to", "type": "address"},
            {"name": "_value", "type": "uint256"}
        ],
        "name": "transfer",
        "outputs": [{"name": "", "type": "bool"}],
        "type": "function"
    }
]

class Word402Agent:
    def __init__(self, target_url, private_key=None, rpc_url=None, simulate=True):
        self.target_url = target_url
        self.private_key = private_key
        self.rpc_url = rpc_url or "https://sepolia.base.org"
        self.simulate = simulate
        self.headers = {
            "User-Agent": "Word402-Autonomous-Agent/1.0",
            "Accept": "text/markdown, application/json"
        }

        if WEB3_AVAILABLE and self.private_key and not self.simulate:
            self.w3 = Web3(Web3.HTTPProvider(self.rpc_url))
            self.account = Account.from_key(self.private_key)
            self.agent_address = self.account.address
        else:
            self.w3 = None
            self.account = None
            self.agent_address = "0xAgentSimulatedWalletAddress12345678901234"

    def log(self, tag, message, color="\033[0m"):
        print(f"{color}[{tag}]\033[0m {message}")

    def _http_get(self, url, headers=None):
        req_headers = dict(self.headers)
        if headers:
            req_headers.update(headers)
        req = urllib.request.Request(url, headers=req_headers, method="GET")
        try:
            with urllib.request.urlopen(req, timeout=15) as res:
                return res.getcode(), res.read().decode("utf-8"), dict(res.headers)
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8"), dict(e.headers)
        except Exception as e:
            return None, str(e), {}

    def run(self):
        print("\n" + "=" * 65)
        self.log("AGENT", f"🤖 자율 AI 에이전트 가동 시작 (Agent Address: {self.agent_address})", "\033[1;36m")
        self.log("TARGET", f"🔗 데이터 리소스 접근 시도: {self.target_url}", "\033[1;34m")
        print("=" * 65 + "\n")

        # Step 1: Initial GET Request
        self.log("HTTP", "1단계: 데이터 엔드포인트에 초기 GET 요청 전송 중...", "\033[33m")
        status, body, resp_headers = self._http_get(self.target_url)

        if status is None:
            self.log("ERROR", f"서버 연결 실패: {body}", "\033[31m")
            return False

        # If already free / 200 OK
        if status == 200:
            self.log("SUCCESS", "✅ 무료 콘텐츠입니다. 즉시 데이터 획득 성공!", "\033[32m")
            self.print_content(body)
            return True

        # Check for HTTP 402
        if status != 402:
            self.log("ERROR", f"예상치 못한 HTTP 응답: {status}\n{body}", "\033[31m")
            return False

        self.log("x402", "🛡️ [HTTP 402 Payment Required] 페이월 감지!", "\033[1;31m")

        # Step 2: Parse Payment Requirements
        try:
            challenge_data = json.loads(body)
        except Exception:
            self.log("ERROR", "402 응답 바디를 JSON으로 파싱할 수 없습니다.", "\033[31m")
            return False

        req = challenge_data.get("payment_requirements", {})
        price = req.get("amount")
        currency = req.get("currency", "USDC")
        network = req.get("network", "base-sepolia")
        recipient = req.get("recipient")
        challenge_id = req.get("challenge_id")
        usdc_contract = req.get("token_address")

        print("\n" + "-" * 50)
        self.log("QUOTE", "📜 결제 요구 명세서 수신 완료:", "\033[1;35m")
        print(f"  • 대상 포스트  : {challenge_data.get('resource', {}).get('title')}")
        print(f"  • 요구 금액    : {price} {currency}")
        print(f"  • 수취인 지갑  : {recipient}")
        print(f"  • 네트워크     : {network}")
        print(f"  • 챌린지 Nonce : {challenge_id}")
        print("-" * 50 + "\n")

        # Step 3: Execute Payment
        self.log("PAYMENT", f"2단계: {price} {currency} 온체인 자율 결제 프로세스 개시...", "\033[33m")
        tx_hash = self.execute_payment(recipient, float(price), usdc_contract, challenge_id)
        if not tx_hash:
            self.log("ERROR", "결제 트랜잭션 전송에 실패했습니다.", "\033[31m")
            return False

        self.log("PAYMENT", f"🚀 온체인 트랜잭션 전송 완료! TX HASH: {tx_hash}", "\033[1;32m")

        # Step 4: Resubmit with Proof
        self.log("RETRY", "3단계: X-Payment 헤더에 결제 영수증을 첨부하여 재요청...", "\033[33m")
        payment_payload = {
            "tx_hash": tx_hash,
            "challenge_id": challenge_id,
            "sender": self.agent_address
        }

        headers = {
            "X-Payment": json.dumps(payment_payload),
            "Accept": "text/markdown"
        }

        retry_status, retry_body, retry_headers = self._http_get(self.target_url, headers=headers)

        if retry_status == 200:
            self.log("SUCCESS", "🎉 [HTTP 200 OK] 온체인 영수증 검증 통과! 데이터 잠금 해제 성공!", "\033[1;32m")
            print("\n" + "=" * 65)
            self.log("PAYLOAD", "📥 수신된 정제 Markdown 데이터 본문:", "\033[1;36m")
            print("=" * 65)
            self.print_content(retry_body)

            # Simulated Agent Intelligence: Auto Summary
            print("\n" + "=" * 65)
            self.log("AI AGENT", "🧠 획득한 데이터를 바탕으로 실시간 요약 브리핑 생성:", "\033[1;35m")
            print("=" * 65)
            lines = [l.strip() for l in retry_body.split("\n") if l.strip() and not l.startswith("#")]
            summary_preview = " ".join(lines[:3]) if lines else "본문 수신 완료."
            print(f"> 에이전트 요약 결과: 본 포스트는 유료 검증을 성공적으로 마쳤으며 핵심 내용은 다음과 같습니다.\n> \"{summary_preview[:200]}...\"\n")
            return True
        else:
            self.log("FAILED", f"❌ 결제 검증 실패 (HTTP {retry_status}): {retry_body}", "\033[31m")
            return False

    def execute_payment(self, recipient, amount, usdc_contract, challenge_id):
        if self.simulate or not self.private_key or not WEB3_AVAILABLE:
            self.log("SIM", "⚡ [시뮬레이션 모드] 가상 온체인 트랜잭션 해시를 생성합니다.", "\033[36m")
            time.sleep(1.0)
            mock_hash = "0x" + os.urandom(32).hex()
            return mock_hash

        try:
            self.log("WEB3", f"온체인 노드({self.rpc_url})에 트랜잭션 서명 및 브로드캐스트 중...", "\033[34m")
            usdc = self.w3.eth.contract(address=Web3.to_checksum_address(usdc_contract), abi=ERC20_TRANSFER_ABI)
            raw_amount = int(round(amount * 1_000_000)) # 6 decimals
            nonce = self.w3.eth.get_transaction_count(self.agent_address)
            gas_price = self.w3.eth.gas_price

            tx = usdc.functions.transfer(
                Web3.to_checksum_address(recipient),
                raw_amount
            ).build_transaction({
                'from': self.agent_address,
                'nonce': nonce,
                'gas': 100000,
                'gasPrice': gas_price,
                'chainId': 84532 # Base Sepolia
            })

            signed_tx = self.w3.eth.account.sign_transaction(tx, private_key=self.private_key)
            tx_hash_bytes = self.w3.eth.send_raw_transaction(signed_tx.rawTransaction)
            tx_hash = self.w3.to_hex(tx_hash_bytes)

            self.log("WEB3", "블록체인 컨펌 대기 중 (Base L2 ~2초)...", "\033[34m")
            self.w3.eth.wait_for_transaction_receipt(tx_hash, timeout=30)
            return tx_hash

        except Exception as e:
            self.log("ERROR", f"Web3 송금 실행 에러: {e}", "\033[31m")
            return None

    def print_content(self, text):
        print("\n" + "-" * 60)
        print(text.strip())
        print("-" * 60 + "\n")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Word402 Autonomous AI Agent Client")
    parser.add_argument("--url", default="http://localhost:8080/wp-json/word402/v1/posts/1", help="Target WordPress REST Endpoint")
    parser.add_argument("--key", default=os.getenv("AGENT_PRIVATE_KEY"), help="Agent Private Key (Optional for live tx)")
    parser.add_argument("--rpc", default="https://sepolia.base.org", help="Base Sepolia RPC Endpoint")
    parser.add_argument("--simulate", action="store_true", default=True, help="Run in simulation mode (No live funds required)")

    args = parser.parse_args()

    agent = Word402Agent(
        target_url=args.url,
        private_key=args.key,
        rpc_url=args.rpc,
        simulate=args.simulate
    )
    agent.run()
