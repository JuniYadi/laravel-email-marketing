# Kubernetes Deployment Guide for AWS EKS

This guide explains how to deploy Laravel Marketing Mail on AWS EKS using IAM Roles for Service Accounts (IRSA) for secure AWS service access.

## Overview

This deployment uses:
- **AWS EKS** - Managed Kubernetes cluster
- **IRSA** - IAM Roles for Service Accounts (no hardcoded credentials)
- **AWS S3** - File storage for email template images
- **AWS SES** - Email sending
- **AWS SNS** - Email event tracking webhooks
- **AWS Load Balancer Controller** - Ingress management

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         AWS EKS Cluster                      │
│                                                              │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────┐  │
│  │   App Pods     │  │  Queue Worker  │  │  Scheduler   │  │
│  │  (replicas: 2) │  │  (replicas: 1) │  │ (replicas: 1)│  │
│  └────────┬───────┘  └────────┬───────┘  └──────┬───────┘  │
│           │                   │                  │          │
│           └───────────────────┴──────────────────┘          │
│                              │                              │
│                    ┌─────────▼──────────┐                   │
│                    │  ServiceAccount    │                   │
│                    │  (IRSA enabled)    │                   │
│                    └─────────┬──────────┘                   │
└──────────────────────────────┼───────────────────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │   IAM Role (IRSA)   │
                    │  - S3 Policy        │
                    │  - SES Policy       │
                    └──────────┬──────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
    ┌─────▼────┐        ┌──────▼─────┐      ┌──────▼─────┐
    │  AWS S3  │        │  AWS SES   │      │  AWS SNS   │
    │  Bucket  │        │  (Emails)  │      │ (Webhooks) │
    └──────────┘        └────────────┘      └────────────┘
```

## Prerequisites

### 1. AWS CLI & eksctl
```bash
# Install AWS CLI
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

# Install eksctl
curl --silent --location "https://github.com/weaveworks/eksctl/releases/latest/download/eksctl_$(uname -s)_amd64.tar.gz" | tar xz -C /tmp
sudo mv /tmp/eksctl /usr/local/bin

# Configure AWS credentials
aws configure
```

### 2. kubectl
```bash
curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
sudo install -o root -g root -m 0755 kubectl /usr/local/bin/kubectl
```

### 3. Helm (for AWS Load Balancer Controller)
```bash
curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
```

---

## Step 1: Create EKS Cluster

### Option A: Using eksctl (Recommended)

```bash
# Create cluster with OIDC provider enabled (required for IRSA)
eksctl create cluster \
  --name marketing-mail-cluster \
  --region us-east-1 \
  --nodegroup-name standard-workers \
  --node-type t3.medium \
  --nodes 2 \
  --nodes-min 2 \
  --nodes-max 4 \
  --with-oidc \
  --managed
```

### Option B: Using existing cluster

```bash
# Associate OIDC provider with existing cluster
eksctl utils associate-iam-oidc-provider \
  --cluster marketing-mail-cluster \
  --region us-east-1 \
  --approve
```

---

## Step 2: Install AWS Load Balancer Controller

```bash
# Add EKS Helm repo
helm repo add eks https://aws.github.io/eks-charts
helm repo update

# Create IAM policy for load balancer controller
curl -o iam_policy.json https://raw.githubusercontent.com/kubernetes-sigs/aws-load-balancer-controller/v2.7.0/docs/install/iam_policy.json

aws iam create-policy \
  --policy-name AWSLoadBalancerControllerIAMPolicy \
  --policy-document file://iam_policy.json

# Create IAM role for service account
eksctl create iamserviceaccount \
  --cluster=marketing-mail-cluster \
  --namespace=kube-system \
  --name=aws-load-balancer-controller \
  --attach-policy-arn=arn:aws:iam::ACCOUNT_ID:policy/AWSLoadBalancerControllerIAMPolicy \
  --override-existing-serviceaccounts \
  --region us-east-1 \
  --approve

# Install controller
helm install aws-load-balancer-controller eks/aws-load-balancer-controller \
  -n kube-system \
  --set clusterName=marketing-mail-cluster \
  --set serviceAccount.create=false \
  --set serviceAccount.name=aws-load-balancer-controller
```

---

## Step 3: Create S3 Bucket

```bash
# Create S3 bucket
aws s3 mb s3://marketing-mail-storage --region us-east-1

# Enable versioning (optional but recommended)
aws s3api put-bucket-versioning \
  --bucket marketing-mail-storage \
  --versioning-configuration Status=Enabled

# Set CORS policy for direct uploads (if needed)
cat > cors.json << 'EOF'
{
  "CORSRules": [
    {
      "AllowedOrigins": ["https://marketing.yourdomain.com"],
      "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
      "AllowedHeaders": ["*"],
      "MaxAgeSeconds": 3000
    }
  ]
}
EOF

aws s3api put-bucket-cors \
  --bucket marketing-mail-storage \
  --cors-configuration file://cors.json
```

---

## Step 4: Create IAM Role for IRSA

### 4.1 Get OIDC Provider URL

```bash
# Get OIDC provider URL
aws eks describe-cluster \
  --name marketing-mail-cluster \
  --query "cluster.identity.oidc.issuer" \
  --output text

# Output example: https://oidc.eks.us-east-1.amazonaws.com/id/EXAMPLED539D4633E53DE1B71EXAMPLE
```

### 4.2 Create IAM Policy

```bash
# Create IAM policy for S3 and SES access
cat > marketing-mail-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "S3BucketAccess",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:PutObjectAcl",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::marketing-mail-storage",
        "arn:aws:s3:::marketing-mail-storage/*"
      ]
    },
    {
      "Sid": "SESEmailSending",
      "Effect": "Allow",
      "Action": [
        "ses:SendEmail",
        "ses:SendRawEmail",
        "ses:GetSendQuota",
        "ses:GetSendStatistics"
      ],
      "Resource": "*"
    }
  ]
}
EOF

aws iam create-policy \
  --policy-name MarketingMailEKSPolicy \
  --policy-document file://marketing-mail-policy.json
```

### 4.3 Create IAM Role with Trust Policy

```bash
# Get your AWS account ID
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

# Get OIDC provider (without https://)
OIDC_PROVIDER=$(aws eks describe-cluster --name marketing-mail-cluster --query "cluster.identity.oidc.issuer" --output text | sed -e "s/^https:\/\///")

# Create trust policy
cat > trust-policy.json << EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::${ACCOUNT_ID}:oidc-provider/${OIDC_PROVIDER}"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "${OIDC_PROVIDER}:sub": "system:serviceaccount:marketing-mail:marketing-mail-sa",
          "${OIDC_PROVIDER}:aud": "sts.amazonaws.com"
        }
      }
    }
  ]
}
EOF

# Create IAM role
aws iam create-role \
  --role-name marketing-mail-eks-pod-role \
  --assume-role-policy-document file://trust-policy.json

# Attach policy to role
aws iam attach-role-policy \
  --role-name marketing-mail-eks-pod-role \
  --policy-arn arn:aws:iam::${ACCOUNT_ID}:policy/MarketingMailEKSPolicy
```

---

## Step 5: Configure Kubernetes Manifests

### 5.1 Update ServiceAccount

Edit `kubernetes/serviceaccount.yaml`:

```yaml
annotations:
  eks.amazonaws.com/role-arn: arn:aws:iam::YOUR_ACCOUNT_ID:role/marketing-mail-eks-pod-role
```

### 5.2 Update ConfigMap

Edit `kubernetes/configmap.yaml` with your settings:
- `APP_URL`
- `AWS_BUCKET`
- `AWS_DEFAULT_REGION`
- `MAIL_FROM_ADDRESS`
- `ALLOWED_DOMAIN`

### 5.3 Update Secret

Edit `kubernetes/secret.yaml`:

```bash
# Generate APP_KEY
php artisan key:generate --show

# Update secret.yaml with:
# - APP_KEY (from above)
# - DB_USERNAME
# - DB_PASSWORD
# - GOOGLE_CLIENT_ID (if using OAuth)
# - GOOGLE_CLIENT_SECRET (if using OAuth)
```

### 5.4 Update Deployment

Edit `kubernetes/deployment.yaml`:
- Replace `ACCOUNT_ID` with your AWS account ID

### 5.5 Update Ingress

Edit `kubernetes/ingress.yaml`:
- Replace `ACCOUNT_ID` and `CERTIFICATE_ID` with your ACM certificate ARN
- Update `host` with your domain

---

## Step 6: Deploy to EKS

```bash
# Update kubeconfig
aws eks update-kubeconfig --name marketing-mail-cluster --region us-east-1

# Apply manifests in order
kubectl apply -f kubernetes/namespace.yaml
kubectl apply -f kubernetes/configmap.yaml
kubectl apply -f kubernetes/secret.yaml
kubectl apply -f kubernetes/serviceaccount.yaml
kubectl apply -f kubernetes/deployment.yaml
kubectl apply -f kubernetes/service.yaml
kubectl apply -f kubernetes/ingress.yaml

# Verify deployment
kubectl get all -n marketing-mail

# Check IRSA configuration
kubectl describe sa marketing-mail-sa -n marketing-mail

# Check pod logs
kubectl logs -n marketing-mail -l app=marketing-mail --tail=50
```

---

## Step 7: Verify IRSA is Working

```bash
# Exec into a pod
kubectl exec -it -n marketing-mail deployment/marketing-mail -- bash

# Inside the pod, check environment variables
env | grep AWS

# You should see:
# AWS_ROLE_ARN=arn:aws:iam::ACCOUNT_ID:role/marketing-mail-eks-pod-role
# AWS_WEB_IDENTITY_TOKEN_FILE=/var/run/secrets/eks.amazonaws.com/serviceaccount/token

# Test S3 access
php artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'Hello from EKS with IRSA!');
>>> Storage::disk('s3')->get('test.txt');

# Test SES access
>>> Mail::raw('Test email', function($m) { $m->to('test@example.com')->subject('Test'); });
```

---

## Step 8: DNS Configuration

```bash
# Get Load Balancer DNS
kubectl get ingress -n marketing-mail marketing-mail-ingress -o jsonpath='{.status.loadBalancer.ingress[0].hostname}'

# Create CNAME record in your DNS:
# marketing.yourdomain.com -> <load-balancer-dns-name>
```

---

## Scaling

### Scale Application Pods
```bash
kubectl scale deployment marketing-mail -n marketing-mail --replicas=3
```

### Scale Queue Workers
```bash
kubectl scale deployment marketing-mail-worker -n marketing-mail --replicas=2
```

### Enable Horizontal Pod Autoscaler
```bash
kubectl autoscale deployment marketing-mail \
  -n marketing-mail \
  --cpu-percent=70 \
  --min=2 \
  --max=10
```

---

## Monitoring

### View Logs
```bash
# App logs
kubectl logs -f -n marketing-mail -l app=marketing-mail

# Queue worker logs
kubectl logs -f -n marketing-mail -l app=marketing-mail-worker

# Scheduler logs
kubectl logs -f -n marketing-mail -l app=marketing-mail-scheduler
```

### Check Resource Usage
```bash
kubectl top pods -n marketing-mail
kubectl top nodes
```

---

## Troubleshooting

### IRSA Not Working

```bash
# Check ServiceAccount annotation
kubectl describe sa marketing-mail-sa -n marketing-mail | grep role-arn

# Check pod environment
kubectl exec -n marketing-mail deployment/marketing-mail -- env | grep AWS

# Check IAM role trust policy
aws iam get-role --role-name marketing-mail-eks-pod-role --query 'Role.AssumeRolePolicyDocument'

# Verify OIDC provider exists
aws iam list-open-id-connect-providers
```

### S3 Access Denied

```bash
# Verify IAM policy attached to role
aws iam list-attached-role-policies --role-name marketing-mail-eks-pod-role

# Check policy permissions
aws iam get-policy-version \
  --policy-arn arn:aws:iam::ACCOUNT_ID:policy/MarketingMailEKSPolicy \
  --version-id v1
```

### SES Sending Failures

```bash
# Check SES sending limits
aws ses get-send-quota

# Verify domain/email is verified
aws ses list-identities

# Check if in sandbox mode
aws ses get-account-sending-enabled
```

### Load Balancer Not Created

```bash
# Check ingress events
kubectl describe ingress -n marketing-mail marketing-mail-ingress

# Check load balancer controller logs
kubectl logs -n kube-system -l app.kubernetes.io/name=aws-load-balancer-controller
```

---

## Updating the Application

```bash
# Update image version in deployment.yaml
# Then apply changes:
kubectl apply -f kubernetes/deployment.yaml

# Or use kubectl set image:
kubectl set image deployment/marketing-mail \
  -n marketing-mail \
  app=ghcr.io/juniyadi/laravel-marketing-mail:v1.2.0

# Rollout status
kubectl rollout status deployment/marketing-mail -n marketing-mail

# Rollback if needed
kubectl rollout undo deployment/marketing-mail -n marketing-mail
```

---

## Clean Up

```bash
# Delete all resources
kubectl delete -f kubernetes/

# Delete cluster
eksctl delete cluster --name marketing-mail-cluster --region us-east-1

# Delete IAM resources
aws iam detach-role-policy \
  --role-name marketing-mail-eks-pod-role \
  --policy-arn arn:aws:iam::ACCOUNT_ID:policy/MarketingMailEKSPolicy

aws iam delete-role --role-name marketing-mail-eks-pod-role
aws iam delete-policy --policy-arn arn:aws:iam::ACCOUNT_ID:policy/MarketingMailEKSPolicy

# Delete S3 bucket (after emptying it)
aws s3 rb s3://marketing-mail-storage --force
```

---

## Security Best Practices

1. **Use IRSA** - Never hardcode AWS credentials
2. **Least Privilege** - Grant minimum required IAM permissions
3. **Network Policies** - Restrict pod-to-pod communication
4. **Secrets Encryption** - Enable EKS secrets encryption at rest
5. **Private Subnets** - Deploy worker nodes in private subnets
6. **Pod Security Standards** - Enable restricted pod security
7. **Regular Updates** - Keep EKS cluster and nodes updated

---

## Cost Optimization

- Use **Spot Instances** for queue workers (non-critical workloads)
- Enable **Cluster Autoscaler** to scale nodes based on demand
- Use **S3 Intelligent-Tiering** for storage cost savings
- Monitor with **AWS Cost Explorer** to identify optimization opportunities

---

## Support

For issues or questions:
- **GitHub Issues**: https://github.com/JuniYadi/laravel-marketing-mail/issues
- **Documentation**: https://github.com/JuniYadi/laravel-marketing-mail
