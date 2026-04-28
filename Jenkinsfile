pipeline {
    environment {
        DISCORD_WEBHOOK_URL = credentials('discord-webhook-url')
        SERVER_IP = credentials('host-ip')
        USER = credentials('user-server')
        PROJECT_NAME = 'Accounting_td'

        DEV_DIR = credentials('development-directory')
        STAGING_DIR = credentials('staging-directory')
        PRODUCTION_DIR = credentials('production-directory')
        MENTION_DISCORD_ID = credentials('mention-discord-id')
    }

    agent any

    stages {
        stage('Deploy') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME
                    def projectDir

                    if (branchName == 'develop') {
                        projectDir = DEV_DIR
                    } else if (branchName == 'staging') {
                        projectDir = STAGING_DIR
                    } else if (branchName == 'main') {
                        projectDir = PRODUCTION_DIR
                    } else {
                        echo "Unsupported branch: ${branchName}"
                        currentBuild.result = 'ABORTED'
                        return
                    }

                    sshagent(credentials: ['jenkins']) {
                        sh "rsync --stats --update --checksum -zrSlhp \
                            --exclude-from=.gitignore \
                            -e \"ssh -p 22 -o StrictHostKeyChecking=no\" \
                            . ${USER}@${SERVER_IP}:${projectDir}"

                        def deployScript = readFile(file: 'scripts/deploy.sh').trim()

                        sh "ssh -p 22 -o StrictHostKeyChecking=no ${USER}@${SERVER_IP} \"cd ${projectDir} && ${deployScript}\""
                    }
                }
            }
        }
    }

    post {
        success {
            script {
                def gitLog = sh(script: 'git log -n 5 --format="%h %s (%an)"', returnStdout: true).trim()

                discordSend description: ">>> **Yay !!!** \nProjectmu udah berhasil di deploy yah \n\n Jenkins Pipeline Build [** FINISHED **] \n```\n${gitLog}\n```",
                            footer: "${env.PROJECT_NAME}",
                            link: env.BUILD_URL,
                            result: currentBuild.currentResult,
                            title: "Deploying ${env.PROJECT_NAME} to ${env.BRANCH_NAME} **SUCCESS**",
                            webhookURL: "${env.DISCORD_WEBHOOK_URL}",
                            thumbnail: "https://media.tenor.com/30TFXsJZzLgAAAAC/happy-anya-spy-x-family.gif",
                            notes: ">>> **Halo kak** ${env.MENTION_DISCORD_ID}"
            }
        }
        failure {
            script {
                def gitLog = sh(script: 'git log -n 5 --format="%h %s (%an)"', returnStdout: true).trim()

                discordSend description: ">>> **Red Arlert,** \nKuleeeee gagal nok, bak cek lagi  \n\n Jenkins Pipeline Build [** FAILED **] \n```\n${gitLog}\n```",
                            footer: "${env.PROJECT_NAME}",
                            link: env.BUILD_URL,
                            result: currentBuild.currentResult,
                            title: "Deploying ${env.PROJECT_NAME} to ${env.BRANCH_NAME} **FAILED**",
                            webhookURL: "${env.DISCORD_WEBHOOK_URL}",
                            thumbnail: "https://media.tenor.com/jW_f0aRGGwcAAAAC/anya-anya-forger.gif",
                            notes: ">>> **Halo kak** ${env.MENTION_DISCORD_ID}"
            }
        }
    }
}